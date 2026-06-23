<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SubscriptionBillingOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\SendOrganizationEmailVerificationJob;

class OrganizationCorporateEmailVerificationController extends Controller
{
    /**
     * Resend or request corporate email verification link.
     */
    public function request(Request $request, string $organization_id): JsonResponse
    {
        $validated = $request->validate([
            'corporate_email' => 'required|email|max:255',
        ]);

        $organization = Organization::query()->find($organization_id);
        if (! $organization) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        if ($organization->owner_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Only the organization owner can verify the corporate email.'], 403);
        }

        if ($organization->isEmailVerified()) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email is already verified.'], 422);
        }

        $corporateEmail = strtolower($validated['corporate_email']);
        $domain = strtolower((string) Str::after($corporateEmail, '@'));
        
        if ($domain !== strtolower((string) $organization->company_domain)) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email domain must match the organization domain.'], 422);
        }

        // Ensure corporate email is unique in organizations
        $existingOrg = Organization::query()
            ->where('email', $corporateEmail)
            ->whereKeyNot($organization->id)
            ->first();

        if ($existingOrg) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email is already in use by another organization.'], 409);
        }

        $order = SubscriptionBillingOrder::query()
            ->where('billable_type', Organization::class)
            ->where('billable_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $order) {
            // If no order exists (should not happen normally), create a pending one
            $plan = $organization->subscription?->plan ?? 'starter';
            $planConfig = config("subscriptions.organizations.{$plan}");
            $amountEgp = (float) ($planConfig['amount_egp'] ?? 0);
            $amountCents = (int) round($amountEgp * 100);

            $order = SubscriptionBillingOrder::query()->create([
                'user_id' => $request->user()->id,
                'billable_type' => Organization::class,
                'billable_id' => $organization->id,
                'workspace_type' => 'organization',
                'plan' => $plan,
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'status' => 'pending',
                'merchant_reference' => (string) Str::uuid(),
            ]);
        }

        DB::transaction(function () use ($organization, $order, $corporateEmail) {
            $organization->update([
                'email' => $corporateEmail,
            ]);

            $order->update([
                'pending_corporate_email' => $corporateEmail,
                'corporate_verification_sent_at' => now(),
            ]);
        });

        SendOrganizationEmailVerificationJob::dispatch($request->user(), $order->id, $corporateEmail);

        return response()->json([
            'status' => 'success',
            'message' => 'Corporate email verification link sent.',
        ]);
    }

    /**
     * Verify the corporate email using the signed link.
     */
    public function verify(Request $request, SubscriptionBillingOrder $billing_order): JsonResponse
    {
        $email = strtolower((string) $request->query('email'));

        if ($billing_order->workspace_type !== 'organization' || $billing_order->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Invalid or already paid organization billing order.'], 422);
        }

        if (! $billing_order->pending_corporate_email || strtolower($billing_order->pending_corporate_email) !== $email) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email does not match this verification request.'], 422);
        }

        /** @var Organization|null $organization */
        $organization = $billing_order->billable;
        if (! $organization instanceof Organization || ! $organization->subscription) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found for this billing order.'], 404);
        }

        $domain = strtolower((string) Str::after($email, '@'));
        if ($domain !== strtolower((string) $organization->company_domain)) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email domain does not match the organization domain.'], 422);
        }

        if ($organization->isEmailVerified()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Corporate email is already verified.',
            ]);
        }

        DB::transaction(function () use ($billing_order, $organization, $email): void {
            // Update organization email and mark verified
            $organization->update([
                'email' => $email,
                'email_verified_at' => now(),
            ]);

            // Mark the verification on the billing order
            $billing_order->update([
                'corporate_email_verified_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Corporate email verified successfully. You can now proceed to payment.',
        ]);
    }
}
