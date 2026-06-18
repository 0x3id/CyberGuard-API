<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class WorkspaceContext
{
    public static function isOrganization(Request $request): bool
    {
        return (bool) $request->attributes->get('is_organization_context', false);
    }

    public static function organization(Request $request): ?Organization
    {
        $organization = $request->attributes->get('organization');

        return $organization instanceof Organization ? $organization : null;
    }

    public static function role(Request $request): ?string
    {
        $role = $request->attributes->get('organization_role');

        return is_string($role) ? $role : null;
    }

    public static function canManageOrganization(Request $request): bool
    {
        return in_array(self::role($request), ['owner', 'admin'], true);
    }

    public static function ownerType(Request $request): string
    {
        return self::isOrganization($request) ? Organization::class : User::class;
    }

    public static function ownerId(Request $request): string
    {
        $organization = self::organization($request);

        return self::isOrganization($request) && $organization
            ? $organization->id
            : $request->user()->id;
    }
}
