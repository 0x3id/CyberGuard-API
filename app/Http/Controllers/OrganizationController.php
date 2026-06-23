<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    /**
     * Get all workspaces the authenticated user belongs to.
     */
    public function getMyWorkspaces(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizations = $user->organizations()->with('subscription')->get();

        return response()->json([
            'status' => 'success',
            'organizations' => $organizations
        ]);
    }

    /**
     * Get organization details and active subscription limits dynamically.
     */
    public function getOrgDetails(Request $request): JsonResponse
    {

        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }


        $organization = $request->attributes->get('organization');
        $organization->load('subscription');

        $plan = $organization->subscription?->plan ?? 'starter';
        $limits = config("org_subscriptions.plans.{$plan}");

        $usage = [
            'projects_count' => $organization->projects()->count(),
            'scans_used' => $organization->subscription?->scans_used_this_month ?? 0,
            'members_count' => $organization->members()->count(),
        ];

        return response()->json([
            'status' => 'success',
            'organization' => $organization,
            'limits' => $limits,
            'usage' => $usage,
        ]);
    }

    /**
     * Update organization metadata.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $role = $request->attributes->get('organization_role');
        if (!in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized to update organization.'], 403);
        }

        $organization = $request->attributes->get('organization');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'logo_url' => 'sometimes|string|url|nullable',
            // company_domain modification is completely prevented.
        ]);

        $organization->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Organization updated successfully.',
            'organization' => $organization
        ]);
    }

    /**
     * Destroy organization and cascade delete tenant data.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $role = $request->attributes->get('organization_role');
        if ($role !== 'owner') {
            return response()->json(['message' => 'Only the owner can delete the organization.'], 403);
        }

        $organization = $request->attributes->get('organization');

        try {
            DB::transaction(function () use ($organization) {
                // Cascade delete organization invitations
                \App\Models\OrganizationInvitation::where('organization_id', $organization->id)->delete();

                $organization->projects()->delete();
                $organization->subscription()->delete();
                $organization->members()->detach();
                $organization->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Organization deleted securely.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete organization.'
            ], 500);
        }
    }
}
