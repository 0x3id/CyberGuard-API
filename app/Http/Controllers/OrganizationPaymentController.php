<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionBillingOrder;
use App\Models\User;
use App\Services\Paymob\PaymobClient;
use App\Support\SubscriptionPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationPaymentController extends Controller
{
    public function __construct(
        private PaymobClient $paymob,
    ) {}

    /**
     * Initiate Paymob checkout for organization subscription
     */
    public function initiateCheckout(Request $request, string $organization_id): JsonResponse
    {
        $validated = $request->validate(array_merge(
            $this->billingDataValidationRules(),
            // ['plan' => 'required|in:starter,pro,enterprise']
        ));

        $user = $request->user();
        $organization = Organization::query()->find($organization_id);

        if (! $organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found',
            ], 404);
        }

        if ($organization->owner_id !== $user->id && ! $organization->hasMember($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $organization->isEmailVerified()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Corporate email must be verified before payment.',
            ], 403);
        }

        $plan = $organization->subscription?->plan ?? 'starter';


        return $this->initiatePaymobCheckout(
            $organization,
            $user,
            $plan,
            $validated['billing_data']
        );
    }

    /**
     * POST /api/organizations/{id}/resume-payment
     *
     * Re-initiates Paymob checkout for verified organizations still in onboarding.
     */
    public function resumePayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->billingDataValidationRules());

        $organization = Organization::query()->find($id);

        if (! $organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found',
            ], 404);
        }

        Gate::authorize('resumePayment', $organization);

        if (! $organization->isEmailVerified()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Corporate email must be verified before payment.',
                'step'    => 'PENDING_VERIFICATION',
            ], 403);
        }

        if ($organization->isSubscriptionActive()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization subscription is already active.',
            ], 422);
        }

        if ($organization->hasSuccessfulCheckout()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A successful checkout already exists for this organization.',
            ], 422);
        }

        $plan = $organization->subscription?->plan ?? 'starter';

        return $this->initiatePaymobCheckout(
            $organization,
            $request->user(),
            $plan,
            $validated['billing_data']
        );
    }

    /**
     * Get payment status for organization subscription
     */
    public function getPaymentStatus(string $organizationId, Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = Organization::find($organizationId);

        if (!$organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found',
            ], 404);
        }

        if ($organization->owner_id !== $user->id && !$organization->hasMember($user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $subscription = $organization->subscription;

        return response()->json([
            'status' => 'success',
            'payment_status' => $subscription->status,
            'plan' => $subscription->plan,
            'expires_at' => $subscription->expires_at,
            'latest_billing_order' => SubscriptionBillingOrder::query()
                ->where('billable_type', Organization::class)
                ->where('billable_id', $organization->id)
                ->latest()
                ->first(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingDataInput
     */
    private function initiatePaymobCheckout(
        Organization $organization,
        User $user,
        string $plan,
        array $billingDataInput
    ): JsonResponse {
        $planConfig = SubscriptionPlans::organization($plan);
        if (! $planConfig) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid plan selected',
            ], 422);
        }

        $amountEgp = (float) ($planConfig['amount_egp'] ?? 0);
        if ($amountEgp <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Plan pricing not configured',
            ], 500);
        }

        $amountCents = (int) round($amountEgp * 100);
        if ($amountCents < 100) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid plan amount',
            ], 500);
        }

        try {
            $subscription = $organization->subscription ?: OrganizationSubscription::query()->create([
                'organization_id' => $organization->id,
                'plan' => $plan,
                'status' => 'pending',
                'max_projects' => $planConfig['max_projects'] ?? 10,
                'max_targets' => $planConfig['max_targets_per_project'] ?? 20,
                'max_members' => $planConfig['max_members'] ?? 5,
                'max_scans_per_month' => $planConfig['max_scans_per_month'] ?? 50,
                'scans_used_this_month' => 0,
                'started_at' => now(),
            ]);

            $subscription->update([
                'plan' => $plan,
                'status' => 'pending',
                'max_projects' => $planConfig['max_projects'],
                'max_targets' => $planConfig['max_targets_per_project'],
                'max_members' => $planConfig['max_members'],
                'max_scans_per_month' => $planConfig['max_scans_per_month'],
            ]);

            $billingOrder = SubscriptionBillingOrder::query()
                ->where('billable_type', Organization::class)
                ->where('billable_id', $organization->id)
                ->where('plan', $plan)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($billingOrder) {
                $merchantReference = $billingOrder->merchant_reference;
            } else {
                $merchantReference = (string) Str::uuid();
                $billingOrder = SubscriptionBillingOrder::query()->create([
                    'user_id' => $user->id,
                    'billable_type' => Organization::class,
                    'billable_id' => $organization->id,
                    'workspace_type' => 'organization',
                    'plan' => $plan,
                    'amount_cents' => $amountCents,
                    'currency' => 'EGP',
                    'status' => 'pending',
                    'merchant_reference' => $merchantReference,
                ]);
            }

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

            $auth = $this->paymob->authToken();
            $items = [[
                'name' => $organization->name.' - '.ucfirst($plan).' plan',
                'amount_cents' => $amountCents,
                'description' => 'Organization subscription order: '.$billingOrder->id,
                'quantity' => '1',
            ]];

            $order = $this->paymob->registerOrder(
                $auth,
                $amountCents,
                'EGP',
                $items,
                $merchantReference,
            );

            $subscription->update([
                'status' => 'pending',
            ]);

            $billingOrder->update(['paymob_order_id' => $order['id']]);

            $paymentToken = $this->paymob->createPaymentKey(
                $auth,
                $amountCents,
                (int) $order['id'],
                'EGP',
                $billingData,
            );

            $iframeUrl = $this->paymob->iframeUrl($paymentToken);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'billing_order_id' => $billingOrder->id,
                    'organization_id' => $organization->id,
                    'merchant_reference' => $merchantReference,
                    'paymob_order_id' => $billingOrder->paymob_order_id,
                    'plan' => $plan,
                    'amount_cents' => $amountCents,
                    'currency' => 'EGP',
                    'iframe_url' => $iframeUrl,
                ],
            ]);
        } catch (RuntimeException $e) {
            Log::warning('Paymob organization checkout failed', [
                'organization_id' => $organization->id,
                'exception' => $e->getMessage(),
            ]);

            if (isset($subscription)) {
                $subscription->update([
                    'status' => 'failed',
                ]);
            }
            if (isset($billingOrder)) {
                $billingOrder->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Payment initialization failed. Please try again.',
            ], 500);
        }
    }

    /**
     * @return array<string, string>
     */
    private function billingDataValidationRules(): array
    {
        return [
            'billing_data' => 'required|array',
            'billing_data.first_name' => 'required|string|max:255',
            'billing_data.last_name' => 'required|string|max:255',
            'billing_data.email' => 'required|email',
            'billing_data.phone_number' => 'required|string|max:20',
            'billing_data.city' => 'required|string|max:255',
            'billing_data.country' => 'required|string|max:255',
            'billing_data.street' => 'sometimes|string|max:255',
            'billing_data.building' => 'sometimes|string|max:255',
            'billing_data.floor' => 'sometimes|string|max:255',
            'billing_data.apartment' => 'sometimes|string|max:255',
            'billing_data.postal_code' => 'sometimes|string|max:20',
        ];
    }
}
