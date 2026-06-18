<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Organization;

class CheckOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $request->header('X-Organization-Id');

        if (! $orgId) {
            $request->attributes->set('is_organization_context', false);
            $request->attributes->set('workspace_type', 'user');
            $request->attributes->set('workspace_owner', $request->user());

            return $next($request);
        } 

        $user = $request->user() ?: auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $organization = Organization::find($orgId);

        if (! $organization || ! $organization->hasMember($user->id)) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden. You are not a member of this organization.'
            ], 403);
        }

        $request->attributes->set('is_organization_context', true);
        $request->attributes->set('workspace_type', 'organization');
        $request->attributes->set('workspace_owner', $organization);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('organization_role', $organization->getMemberRole($user->id));

        return $next($request);
    }
}
