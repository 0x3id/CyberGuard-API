<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationOnboardingController extends Controller
{
    /**
     * Initiate the organization creation process.
     * This creates the pending organization and prepares it for Paymob payment.
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'org_name' => 'required|string|max:255',
            'company_domain' => 'required|string|max:255',
            'plan' => 'required|in:starter,pro,enterprise'
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            // 2. Create the Organization (Pending Payment state)
            // Notice we do NOT attach the user to organization_members yet. 
            // That happens after payment via webhook.
            $organization = Organization::create([
                'owner_id' => $user->id,
                'name' => $validated['org_name'],
                'slug' => Str::slug($validated['org_name']) . '-' . Str::random(6),
                'domain' => $validated['company_domain'],
            ]);

            // 3. Create the Subscription (Pending state with Paymob as payment method)
            $limits = config("subscriptions.organizations.{$validated['plan']}");
            
            OrganizationSubscription::create([
                'organization_id' => $organization->id,
                'plan' => $validated['plan'],
                'status' => 'pending', // Will become active after Paymob payment
                'max_projects' => $limits['max_projects'] ?? 10,
                'max_targets' => $limits['max_targets_per_project'] ?? 20,
                'max_members' => $limits['max_members'] ?? 5,
                'max_scans_per_month' => $limits['max_scans_per_month'] ?? 50,
                'scans_used_this_month' => 0,
                'started_at' => now(),
            ]);

            DB::commit();

            // 4. Return organization details for payment checkout
            return response()->json([
                'status' => 'success',
                'message' => 'Organization created in pending state. Proceed to payment...',
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'plan' => $validated['plan'],
                'next_step' => 'Use the /organizations/{organization_id}/payment/checkout endpoint to initialize Paymob payment',
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
