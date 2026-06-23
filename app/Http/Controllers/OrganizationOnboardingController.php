<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionBillingOrder;
use App\Jobs\SendOrganizationEmailVerificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationOnboardingController extends Controller
{
    /**
     * Initiate the organization creation process.
     * This creates the pending organization, subscription, pending billing order,
     * and sends the pre-payment corporate email verification.
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'org_name' => 'required|string|max:255',
            'company_domain' => 'required|string|max:255',
            'plan' => 'required|in:starter,pro,enterprise',
            'corporate_email' => 'required|email|max:255|unique:organizations,email',
        ]);

        $user = $request->user();
        $corporateEmail = strtolower($validated['corporate_email']);
        $companyDomain = strtolower($validated['company_domain']);
        $emailDomain = strtolower(Str::after($corporateEmail, '@'));

        if ($emailDomain !== $companyDomain) {
            return response()->json([
                'status' => 'error',
                'message' => 'Corporate email domain must match the company domain.',
            ], 422);
        }

        $planConfig = config("subscriptions.organizations.{$validated['plan']}");
        if (!$planConfig) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid plan selected',
            ], 422);
        }

        $amountEgp = (float) ($planConfig['amount_egp'] ?? 0);
        $amountCents = (int) round($amountEgp * 100);

        try {
            DB::beginTransaction();

            // 1. Create the Organization (email_verified_at is null, pending verification)
            $organization = Organization::create([
                'owner_id' => $user->id,
                'name' => $validated['org_name'],
                'slug' => Str::slug($validated['org_name']) . '-' . Str::random(6),
                'company_domain' => $companyDomain,
                'email' => $corporateEmail,
                'email_verified_at' => null,
            ]);

            // 2. Create the Subscription (Pending state)
            OrganizationSubscription::create([
                'organization_id' => $organization->id,
                'plan' => $validated['plan'],
                'status' => 'pending',
                'max_projects' => $planConfig['max_projects'] ?? 10,
                'max_targets' => $planConfig['max_targets_per_project'] ?? 20,
                'max_members' => $planConfig['max_members'] ?? 5,
                'max_scans_per_month' => $planConfig['max_scans_per_month'] ?? 50,
                'scans_used_this_month' => 0,
                'started_at' => now(),
            ]);

            // 3. Create a pending SubscriptionBillingOrder to track verification pre-payment
            $merchantReference = (string) Str::uuid();
            $billingOrder = SubscriptionBillingOrder::create([
                'user_id' => $user->id,
                'billable_type' => Organization::class,
                'billable_id' => $organization->id,
                'workspace_type' => 'organization',
                'plan' => $validated['plan'],
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'status' => 'pending',
                'merchant_reference' => $merchantReference,
                'pending_corporate_email' => $corporateEmail,
                'corporate_verification_sent_at' => now(),
            ]);

            DB::commit();

            // 4. Dispatch the verification email job
            SendOrganizationEmailVerificationJob::dispatch($user, $billingOrder->id, $corporateEmail);

            return response()->json([
                'status' => 'success',
                'message' => 'Organization created in pending state. Verification email sent to ' . $corporateEmail,
                'organization_id' => $organization->id,
                'billing_order_id' => $billingOrder->id,
                'plan' => $validated['plan'],
                'next_step' => 'Verify your email by clicking the link sent to your corporate email, then proceed to checkout.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate onboarding: ' . $e->getMessage()
            ], 500);
        }
    }
}
