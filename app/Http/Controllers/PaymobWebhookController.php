<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionBillingOrder;
use App\Models\UserSubscription;
use App\Services\Paymob\PaymobTransactionHmac;
use App\Support\SubscriptionPlanLimits;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymobWebhookController extends Controller
{
    public function __construct(
        private PaymobTransactionHmac $hmac,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->all();
        if (($payload['type'] ?? null) !== 'TRANSACTION') {
            return response()->noContent();
        }

        if (! $this->hmac->verifyTransaction($payload)) {
            Log::warning('Paymob webhook rejected: invalid HMAC');

            return response('Invalid HMAC', 401);
        }

        $obj = $payload['obj'] ?? null;
        if (! is_array($obj)) {
            return response()->noContent();
        }

        $orderData = $obj['order'] ?? null;
        if (! is_array($orderData)) {
            return response()->noContent();
        }

        $paymobOrderId = $orderData['id'] ?? null;
        $merchantRef = $orderData['merchant_order_id'] ?? null;

        $billingOrder = null;
        if (is_int($paymobOrderId) || is_string($paymobOrderId)) {
            $billingOrder = SubscriptionBillingOrder::query()
                ->where('paymob_order_id', $paymobOrderId)
                ->first();
        }
        if (! $billingOrder && (is_string($merchantRef) || is_int($merchantRef))) {
            $billingOrder = SubscriptionBillingOrder::query()
                ->where('merchant_reference', (string) $merchantRef)
                ->first();
        }

        if (! $billingOrder) {
            Log::info('Paymob webhook: no matching billing order', compact('paymobOrderId', 'merchantRef'));

            return response()->noContent();
        }

        $billingOrder->last_paymob_payload = $payload;
        $billingOrder->save();

        $txnId = $obj['id'] ?? null;
        if (is_int($txnId) || is_string($txnId)) {
            $billingOrder->paymob_transaction_id = (int) $txnId;
            $billingOrder->save();
        }

        $success = $obj['success'] ?? false;
        if ($success !== true && $success !== 'true') {
            $billingOrder->update([
                'status' => 'failed',
                'failure_reason' => is_array($obj['data'] ?? null)
                    ? (string) json_encode($obj['data'])
                    : 'Payment not successful',
            ]);

            return response()->noContent();
        }

        if ($billingOrder->isPaid()) {
            return response()->noContent();
        }

        $user = $billingOrder->user;
        $limits = SubscriptionPlanLimits::forPlan($billingOrder->plan);
        $months = max(1, (int) config('paymob.billing_period_months', 1));

        DB::transaction(function () use ($billingOrder, $user, $limits, $months): void {
            $lockedOrder = SubscriptionBillingOrder::query()
                ->whereKey($billingOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || $lockedOrder->isPaid()) {
                return;
            }

            $subscription = UserSubscription::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                Log::error('Paymob webhook: user missing subscription row', ['user_id' => $user->id]);

                return;
            }

            $lockedOrder->update([
                'status' => 'paid',
                'paid_at' => now(),
                'failure_reason' => null,
            ]);

            $subscription->update([
                'plan' => $lockedOrder->plan,
                'status' => 'active',
                'max_projects' => $limits['max_projects'],
                'max_targets' => $limits['max_targets'],
                'max_scans_per_month' => $limits['max_scans_per_month'],
                'started_at' => now(),
                'expires_at' => now()->addMonths($months),
            ]);
        });

        return response()->noContent();
    }
}
