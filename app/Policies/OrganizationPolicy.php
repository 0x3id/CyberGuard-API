<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Only the original organization owner may restore a soft-deleted workspace.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id;
    }

    /**
     * Only the original organization owner may permanently purge a workspace.
     */
    public function forceDelete(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id;
    }

    /**
     * Only the owner may re-send corporate email verification during onboarding.
     */
    public function resendVerification(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id;
    }

    /**
     * Only the owner may resume payment for a verified, pending organization.
     */
    public function resumePayment(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id;
    }
}
