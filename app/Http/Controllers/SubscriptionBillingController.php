<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingCheckoutRequest;
use App\Models\SubscriptionBillingOrder;
use App\Models\User;
use App\Services\Paymob\PaymobClient;
use App\Support\SubscriptionPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionBillingController extends Controller
{
    public function __construct(
        private PaymobClient $paymob,
    ) {}

    public function checkout(BillingCheckoutRequest $request): JsonResponse
    {
        $plan = $request->validated('plan');
        $billingDataInput = $request->validated('billing_data');
        $planConfig = SubscriptionPlans::user($plan);

        $amountEgp = (float) $planConfig['amount_egp'];
        $amountCents = (int) round($amountEgp * 100);
        if ($amountCents < 100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configured plan amount is invalid.',
            ], 500);
        }

        $billingOrder = SubscriptionBillingOrder::query()->create([
            'user_id' => $request->user()->id,
            'billable_type' => User::class,
            'billable_id' => $request->user()->id,
            'workspace_type' => 'user',
            'plan' => $plan,
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'status' => 'pending',
            'merchant_reference' => (string) Str::uuid(),
        ]);

        $billingData = [
            'apartment' => $billingDataInput['apartment'] ?? 'NA',
            'email' => $billingDataInput['email'],
            'floor' => $billingDataInput['floor'] ?? 'NA',
            'first_name' => $billingDataInput['first_name'],
            'street' => $billingDataInput['street'] ?? 'NA',
            'building' => $billingDataInput['building'] ?? 'NA',
            'phone_number' => $billingDataInput['phone_number'],
            'shipping_method' => 'NA',
            'postal_code' => $billingDataInput['postal_code'] ?? 'NA',
            'city' => $billingDataInput['city'],
            'country' => strtoupper($billingDataInput['country']),
            'last_name' => $billingDataInput['last_name'],
        ];

        try {
            $auth = $this->paymob->authToken();
            $items = [[
                'name' => 'CyberGuard '.ucfirst($plan).' plan',
                'amount_cents' => $amountCents,
                'description' => 'Subscription '.$billingOrder->id,
                'quantity' => '1',
            ]];

            $order = $this->paymob->registerOrder(
                $auth,
                $amountCents,
                'EGP',
                $items,
                $billingOrder->merchant_reference,
            );

            $billingOrder->paymob_order_id = $order['id'];
            $billingOrder->save();

        $paymentToken = $this->paymob->createPaymentKey(
                $auth,
                $amountCents,
                (int) $order['id'],
                'EGP',
                $billingData,
            );

            $iframeUrl = $this->paymob->iframeUrl($paymentToken);
        } catch (RuntimeException $e) {
            Log::warning('Paymob checkout failed', ['exception' => $e->getMessage()]);
            $billingOrder->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not start payment. Check Paymob configuration and try again.',
            ], 502);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'billing_order_id' => $billingOrder->id,
                'merchant_reference' => $billingOrder->merchant_reference,
                'paymob_order_id' => $billingOrder->paymob_order_id,
                'plan' => $plan,
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'iframe_url' => $iframeUrl,
            ],
        ]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'currency' => 'EGP',
                'user_plans' => collect(SubscriptionPlans::all('users'))
                    ->map(fn (array $plan, string $id) => [
                        'id' => $id,
                        'amount_egp' => (float) $plan['amount_egp'],
                        'checkout_available' => ((float) $plan['amount_egp']) > 0,
                        'limits' => $plan,
                    ])
                    ->values(),
                'organization_plans' => collect(SubscriptionPlans::all('organizations'))
                    ->map(fn (array $plan, string $id) => [
                        'id' => $id,
                        'amount_egp' => (float) $plan['amount_egp'],
                        'checkout_available' => true,
                        'limits' => $plan,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $userOrders = SubscriptionBillingOrder::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_type', 'user')
            ->latest()
            ->limit(50)
            ->get([
                'id',
                'plan',
                'amount_cents',
                'currency',
                'status',
                'merchant_reference',
                'paymob_order_id',
                'paymob_transaction_id',
                'paid_at',
                'failure_reason',
                'created_at',
            ]);

            $orgOrders = SubscriptionBillingOrder::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_type', 'organization')
            ->latest()
            ->limit(50)
            ->get([
                'id',
                'plan',
                'amount_cents',
                'currency',
                'status',
                'merchant_reference',
                'paymob_order_id',
                'paymob_transaction_id',
                'paid_at',
                'failure_reason',
                'created_at',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_orders' => $userOrders,
                'organization_orders' => $orgOrders,
            ]
        ]);
    }
}
