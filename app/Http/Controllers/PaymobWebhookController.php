<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SubscriptionBillingOrder;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Paymob\PaymobTransactionHmac;
use App\Support\SubscriptionPlanLimits;
use App\Support\SubscriptionPlans;
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
        $orderData = is_array($obj) ? ($obj['order'] ?? null) : null;

        if (! is_array($obj) || ! is_array($orderData)) {
            return response()->noContent();
        }

        $paymobOrderId = $orderData['id'] ?? null;
        $merchantRef = $orderData['merchant_order_id'] ?? null;

        $billingOrder = $this->findBillingOrder($paymobOrderId, $merchantRef);

        if (! $billingOrder) {
            Log::info('Paymob webhook: no matching billing order', compact('paymobOrderId', 'merchantRef'));

            return response()->noContent();
        }

        $billingOrder->update([
            'last_paymob_payload' => $payload,
            'paymob_transaction_id' => is_int($obj['id'] ?? null) || is_string($obj['id'] ?? null)
                ? (int) $obj['id']
                : $billingOrder->paymob_transaction_id,
        ]);

        $success = $obj['success'] ?? false;
        if ($success !== true && $success !== 'true') {
            $billingOrder->update([
                'status' => 'failed',
                'failure_reason' => is_array($obj['data'] ?? null)
                    ? (string) json_encode($obj['data'])
                    : (string) ($obj['error'] ?? 'Payment not successful'),
            ]);

            return response()->noContent();
        }

        if ($billingOrder->isPaid()) {
            return response()->noContent();
        }

        DB::transaction(function () use ($billingOrder): void {
            $lockedOrder = SubscriptionBillingOrder::query()
                ->with('billable')
                ->whereKey($billingOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || $lockedOrder->isPaid()) {
                return;
            }

            $lockedOrder->update([
                'status' => 'paid',
                'paid_at' => now(),
                'failure_reason' => null,
            ]);

            if ($lockedOrder->billable instanceof User) {
                $this->activateUserSubscription($lockedOrder);

                return;
            }

            if ($lockedOrder->billable instanceof Organization) {
                $this->activateOrgSubscription($lockedOrder);
            }
        });

        return response()->noContent();
    }

    private function findBillingOrder(mixed $paymobOrderId, mixed $merchantRef): ?SubscriptionBillingOrder
    {
        if (is_int($paymobOrderId) || is_string($paymobOrderId)) {
            $order = SubscriptionBillingOrder::query()
                ->where('paymob_order_id', $paymobOrderId)
                ->first();

            if ($order) {
                return $order;
            }
        }

        if (is_int($merchantRef) || is_string($merchantRef)) {
            return SubscriptionBillingOrder::query()
                ->where('merchant_reference', (string) $merchantRef)
                ->first();
        }

        return null;
    }

    private function activateUserSubscription(SubscriptionBillingOrder $order): void
    {
        /** @var User $user */
        $user = $order->billable;
        $limits = SubscriptionPlanLimits::forPlan($order->plan);
        $planConfig = SubscriptionPlans::user($order->plan);
        $months = max(1, (int) config('paymob.billing_period_months', 1));

        UserSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan' => $order->plan,
                'status' => 'active',
                'max_projects' => $limits['max_projects'],
                'max_collaborate_in_projects' => $planConfig['max_collaborate_in_projects'],
                'max_targets' => $limits['max_targets'],
                'max_targets_per_project' => $limits['max_targets'],
                'max_scans_per_month' => $limits['max_scans_per_month'],
                'started_at' => now(),
                'expires_at' => now()->addMonths($months),
            ]
        );
    }

    private function activateOrgSubscription(SubscriptionBillingOrder $order): void
    {
        /** @var Organization $organization */
        $organization = $order->billable;
        $limits = SubscriptionPlans::organization($order->plan);
        $months = max(1, (int) config('paymob.billing_period_months', 1));

        $subscription = $organization->subscription()->lockForUpdate()->first();
        if (! $subscription) {
            Log::error('Paymob webhook: organization missing subscription row', [
                'organization_id' => $organization->id,
                'billing_order_id' => $order->id,
            ]);

            return;
        }

        $subscription->update([
            'plan' => $order->plan,
            'status' => 'active',
            'max_projects' => $limits['max_projects'],
            'max_targets' => $limits['max_targets_per_project'],
            'max_members' => $limits['max_members'],
            'max_scans_per_month' => $limits['max_scans_per_month'],
            'started_at' => now(),
            'expires_at' => now()->addMonths($months),
        ]);

        $organization->members()->syncWithoutDetaching([
            $organization->owner_id => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'role' => 'owner',
                'joined_at' => now(),
            ],
        ]);
    }
}
