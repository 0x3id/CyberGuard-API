<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Project;
use App\Models\Target;
use App\Support\SubscriptionPlans;
use App\Support\WorkspaceContext;

class TargetPolicy
{
    public function create(User $user, Project $project)
    {
        $request = request();

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            if (! $organization || ! WorkspaceContext::canManageOrganization($request)) {
                return false;
            }

            if ($project->owner_type !== \App\Models\Organization::class || $project->owner_id !== $organization->id) {
                return false;
            }

            $plan = $organization->subscription?->plan ?? 'starter';
            $maxTargets = (int) SubscriptionPlans::organization($plan)['max_targets_per_project'];
            $currentTargets = $project->targets()->count();

            if ($currentTargets >= $maxTargets) {
                abort(response()->json([
                    'status' => false,
                    'message' => 'Target limit reached for this project under the current subscription tier.'
                ], 422));
            }

            return true;
        }

        if (! $project->hasAccess($user->id) || $project->getUserRole($user->id) === 'viewer') {
            return false;
        }

        $plan = $user->subscription?->plan ?? 'free';
        $maxTargets = (int) SubscriptionPlans::user($plan)['max_targets_per_project'];

        if ($project->targets()->count() >= $maxTargets) {
            abort(response()->json([
                'status' => false,
                'message' => 'Target limit reached for this project under your subscription tier.'
            ], 422));
        }

        return true;
    }

    public function manage(User $user, Target $target): bool
    {
        
        $project = $target->project;
        $request = request();

        if (! $project) {
            return false;
        }

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            return $organization
                && WorkspaceContext::canManageOrganization($request)
                && $project->owner_type === \App\Models\Organization::class
                && $project->owner_id === $organization->id;
        }
        
        return $project->hasAccess($user->id) && $project->getUserRole($user->id) !== 'viewer';
    }
}
