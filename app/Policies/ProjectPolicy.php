<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\SubscriptionPlans;
use App\Support\WorkspaceContext;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        $request = request();

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            if (! $organization || ! WorkspaceContext::canManageOrganization($request)) {
                return false;
            }

            $plan = $organization->subscription?->plan ?? 'starter';
            $limits = SubscriptionPlans::organization($plan);
            $maxProjects = (int) $limits['max_projects'];
            $currentProjects = $organization->projects()->count();

            if ($currentProjects >= $maxProjects) {
                abort(response()->json([
                    'status' => false,
                    'message' => 'Subscription limit reached for maximum projects.'
                ], 422));
            }

            return true;
        }

        // Individual space limits
        if (!$user->canCreateProject()) {
            abort(response()->json([
                'status' => false,
                'message' => 'Personal subscription limit reached for maximum projects.'
            ], 422));
        }

        return true;
    }

    public function view(User $user, Project $project): bool
    {
        $request = request();

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            return $organization
                && $project->owner_type === \App\Models\Organization::class
                && $project->owner_id === $organization->id
                && $organization->hasMember($user->id);
        }

        return $project->owner_type === User::class
            && $project->hasAccess($user->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project)
    {
        $request = request();

        if (WorkspaceContext::isOrganization($request)) {
            $organization = WorkspaceContext::organization($request);

            if (! $organization || $project->owner_type !== \App\Models\Organization::class || $project->owner_id !== $organization->id) {
                return false;
            }

            return WorkspaceContext::canManageOrganization($request);
        }

        return $project->owner_type === User::class && $project->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project)
    {
        return $this->update($user, $project);
    }
}
