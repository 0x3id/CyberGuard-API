<?php

namespace App\Http\Controllers;

use App\Jobs\SendOrganizationEmailVerificationJob;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\SubscriptionBillingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class OrganizationController extends Controller
{
    /**
     * GET /api/organizations/my-workspaces
     *
     * Returns active memberships, owner onboarding drafts, and owner recycle-bin rows.
     */
    public function getMyWorkspaces(Request $request): JsonResponse
    {
        $user = $request->user();

        $active = Organization::query()
            ->with('subscription')
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('subscription', fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => $this->formatWorkspace($organization))
            ->values();

        $pending = Organization::query()
            ->with('subscription')
            ->where('owner_id', $user->id)
            ->where(function ($query): void {
                $query->whereDoesntHave('subscription')
                    ->orWhereHas('subscription', fn ($subscriptionQuery) => $subscriptionQuery->where('status', '!=', 'active'));
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Organization $organization) => $this->formatWorkspace(
                $organization,
                $organization->resolveOnboardingStep()
            ))
            ->values();

        $deleted = Organization::query()
            ->onlyTrashed()
            ->with('subscription')
            ->where('owner_id', $user->id)
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Organization $organization) => $this->formatWorkspace($organization))
            ->values();

        return response()->json([
            'status'  => 'success',
            'active'  => $active,
            'pending' => $pending,
            'deleted' => $deleted,
        ]);
    }

    /**
     * POST /api/organizations/{id}/resend-verification
     */
    public function resendVerification(Request $request, string $id): JsonResponse
    {
        $organization = Organization::query()->find($id);

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization not found.',
            ], 404);
        }

        Gate::authorize('resendVerification', $organization);

        if ($organization->isEmailVerified()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Corporate email is already verified.',
            ], 422);
        }

        if ($organization->isSubscriptionActive()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization is already active.',
            ], 422);
        }

        $corporateEmail = strtolower((string) $organization->email);
        if ($corporateEmail === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization corporate email is missing.',
            ], 422);
        }

        $billingOrder = SubscriptionBillingOrder::query()
            ->where('billable_type', Organization::class)
            ->where('billable_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $billingOrder) {
            $plan = $organization->subscription?->plan ?? 'starter';
            $planConfig = config("subscriptions.organizations.{$plan}");
            $amountEgp = (float) ($planConfig['amount_egp'] ?? 0);
            $amountCents = (int) round($amountEgp * 100);

            $billingOrder = SubscriptionBillingOrder::query()->create([
                'user_id'            => $request->user()->id,
                'billable_type'      => Organization::class,
                'billable_id'        => $organization->id,
                'workspace_type'     => 'organization',
                'plan'               => $plan,
                'amount_cents'       => $amountCents,
                'currency'           => 'EGP',
                'status'             => 'pending',
                'merchant_reference' => (string) Str::uuid(),
                'pending_corporate_email' => $corporateEmail,
            ]);
        }

        $billingOrder->update([
            'pending_corporate_email'        => $corporateEmail,
            'corporate_verification_sent_at' => now(),
        ]);

        SendOrganizationEmailVerificationJob::dispatch(
            $request->user(),
            $billingOrder->id,
            $corporateEmail
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'A new corporate email verification link has been sent.',
            'data'    => [
                'organization_id'  => $organization->id,
                'corporate_email'  => $corporateEmail,
                'billing_order_id' => $billingOrder->id,
            ],
        ]);
    }

    /**
     * POST /api/organizations/{id}/restore
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $organization = Organization::query()->onlyTrashed()->find($id);

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Deleted organization not found.',
            ], 404);
        }

        Gate::authorize('restore', $organization);

        try {
            DB::transaction(function () use ($organization): void {
                $organization->restore();
                $organization->projects()->onlyTrashed()->restore();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Organization restored successfully.',
                'data'    => $this->formatWorkspace($organization->fresh(['subscription'])),
            ]);
        } catch (Throwable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to restore organization.',
            ], 500);
        }
    }

    /**
     * DELETE /api/organizations/{id}/force
     */
    public function forceDestroy(Request $request, string $id): JsonResponse
    {
        $organization = Organization::query()->onlyTrashed()->find($id);

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Deleted organization not found.',
            ], 404);
        }

        Gate::authorize('forceDelete', $organization);

        try {
            DB::transaction(function () use ($organization): void {
                OrganizationInvitation::query()
                    ->where('organization_id', $organization->id)
                    ->delete();

                $organization->projects()->withTrashed()->forceDelete();
                $organization->subscription()?->delete();

                SubscriptionBillingOrder::query()
                    ->where('billable_type', Organization::class)
                    ->where('billable_id', $organization->id)
                    ->delete();

                $organization->members()->detach();
                $organization->forceDelete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Organization permanently deleted.',
            ]);
        } catch (Throwable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to permanently delete organization.',
            ], 500);
        }
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

                // $organization->projects()->delete();
                // $organization->subscription()->delete();
                // $organization->members()->detach();
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

    /**
     * DELETE /api/organizations/{id}/pending
     * Delete pending organization
     */
    public function deletePending(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $organization = Organization::query()->find($id);

        if (! $organization) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organization not found.',
            ], 404);
        }

        if ($organization->owner_id !== $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only the owner can delete this organization.',
            ], 403);
        }

        if ($organization->isSubscriptionActive()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Active organizations cannot be deleted through this endpoint.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($organization): void {
                OrganizationInvitation::query()
                    ->where('organization_id', $organization->id)
                    ->delete();

                $organization->projects()->forceDelete();
                $organization->subscription()?->delete();

                SubscriptionBillingOrder::query()
                    ->where('billable_type', Organization::class)
                    ->where('billable_id', $organization->id)
                    ->delete();

                $organization->members()->detach();
                $organization->forceDelete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Pending organization deleted successfully.',
            ]);
        } catch (Throwable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete pending organization.',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkspace(Organization $organization, ?string $step = null): array
    {
        $payload = [
            'id'                => $organization->id,
            'name'              => $organization->name,
            'slug'              => $organization->slug,
            'company_domain'    => $organization->company_domain,
            'email'             => $organization->email,
            'email_verified_at' => $organization->email_verified_at?->toIso8601String(),
            'logo_url'          => $organization->logo_url,
            'owner_id'          => $organization->owner_id,
            'created_at'        => $organization->created_at?->toIso8601String(),
            'subscription'      => $organization->subscription ? [
                'id'     => $organization->subscription->id,
                'plan'   => $organization->subscription->plan,
                'status' => $organization->subscription->status,
            ] : null,
        ];

        if ($step !== null) {
            $payload['step'] = $step;
        }

        if ($organization->trashed()) {
            $payload['deleted_at'] = $organization->deleted_at?->toIso8601String();
        }

        return $payload;
    }
}
