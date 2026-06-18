<?php

namespace App\Policies;

use App\Models\Target;
use App\Models\User;
use App\Support\SubscriptionPlans;
use App\Support\WorkspaceContext;

class ScanPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Target $target = null)
    {
        
        $request = request();
        if (! $target || ! $target->project || ! $target->is_verified) {
            return false;
        }

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            if (! $organization || ! WorkspaceContext::canManageOrganization($request)) {
                return false;
            }

            if ($target->project->owner_type !== \App\Models\Organization::class || $target->project->owner_id !== $organization->id) {
                return false;
            }

            $plan = $organization->subscription?->plan ?? 'starter';
            $maxScans = (int) SubscriptionPlans::organization($plan)['max_scans_per_month'];
            $scansUsed = $organization->subscription?->scans_used_this_month ?? 0;

            if ($scansUsed >= $maxScans) {
                abort(response()->json([
                    'status' => false,
                    'message' => 'Monthly scan limit reached for your subscription tier.'
                ], 422));
            }

            return true;
        }

        if (! $target->project->hasAccess($user->id) || $target->project->getUserRole($user->id) === 'viewer' || ! $target->project->owner_type !== User::class) {
            return false;
        }

        $plan = $user->subscription?->plan ?? 'free';
        $maxScans = (int) SubscriptionPlans::user($plan)['max_scans_per_month'];
        $scansUsed = $user->subscription?->scans_used_this_month ?? 0;

        if ($scansUsed >= $maxScans) {
            abort(response()->json([
                'status' => false,
                'message' => 'Monthly scan limit reached for your subscription tier.'
            ], 422));
        }

        return true;
    }
}
