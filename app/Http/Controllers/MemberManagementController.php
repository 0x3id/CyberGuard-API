<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\User;
use App\Models\OrganizationInvitation;
use App\Jobs\SendOrganizationInvitationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MemberManagementController extends Controller
{
    /**
     * Invite a new member.
     */
    public function invite(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $organization = $request->attributes->get('organization');
        $role = $request->attributes->get('organization_role');

        if (!in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized to invite members.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member,viewer'
        ]);

        // Domain Constraint Check
        $emailDomain = strtolower(substr(strrchr($validated['email'], "@"), 1));
        if ($emailDomain !== strtolower((string) $organization->company_domain)) {
            return response()->json(['message' => 'Domain constraint failed. Email must match company domain.'], 422);
        }

        // Check Max Members Threshold
        $plan = $organization->subscription?->plan ?? 'starter';
        $maxMembers = config("org_subscriptions.plans.{$plan}.max_members", 10);
        
        $currentMembersCount = $organization->members()->count();
        $pendingInvitationsCount = OrganizationInvitation::where('organization_id', $organization->id)
                                    ->where('expires_at', '>', now())
                                    ->count();

        if (($currentMembersCount + $pendingInvitationsCount) >= $maxMembers) {
            return response()->json(['message' => 'Maximum members threshold reached for your tier (including pending invitations).'], 422);
        }

        // Check if user is already a member
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $organization->hasMember($existingUser->id)) {
            return response()->json(['message' => 'User is already a member.'], 422);
        }

        // Check if invitation already exists
        $existingInvitation = OrganizationInvitation::where('organization_id', $organization->id)
                                ->where('email', $validated['email'])
                                ->first();

        if ($existingInvitation) {
            if (!$existingInvitation->isExpired()) {
                return response()->json(['message' => 'An active invitation has already been sent to this email.'], 422);
            }
            // Delete expired invitation to create a new one
            $existingInvitation->delete();
        }

        // Create Invitation
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Str::random(60),
            'expires_at' => now()->addHours(24)
        ]);

        // Dispatch Email Job Async
        SendOrganizationInvitationJob::dispatch($invitation);

        return response()->json([
            'status' => 'success',
            'message' => 'Invitation sent successfully.',
            'invitation_id' => $invitation->id
        ], 200);
    }

    /**
     * List current members.
     */
    public function list(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $organization = $request->attributes->get('organization');
        return response()->json(['status' => 'success', 'members' => $organization->members]);
    }

    /**
     * List pending invitations.
     */
    public function listInvitations(Request $request): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $organization = $request->attributes->get('organization');
        $role = $request->attributes->get('organization_role');

        if (!in_array($role, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized to view invitations.'], 403);
        }

        $invitations = OrganizationInvitation::where('organization_id', $organization->id)
            ->where('expires_at', '>', now())
            ->get();

        return response()->json(['status' => 'success', 'invitations' => $invitations]);
    }

    /**
     * Update member role.
     */
    public function updateRole(Request $request, string $userId): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $organization = $request->attributes->get('organization');
        $myRole = $request->attributes->get('organization_role');

        if (!in_array($myRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized to update roles.'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,member,viewer'
        ]);

        $targetMemberRole = $organization->getMemberRole($userId);
        
        if (!$targetMemberRole) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        // Prevent tampering with owner's role
        if ($targetMemberRole === 'owner') {
            return response()->json(['message' => 'Cannot modify the role of an owner.'], 403);
        }

        $organization->members()->updateExistingPivot($userId, ['role' => $validated['role']]);

        return response()->json(['status' => 'success', 'message' => 'Member role updated successfully.']);
    }

    /**
     * Remove member and revoke tokens.
     */
    public function remove(Request $request, string $userId): JsonResponse
    {
        if (!$request->attributes->get('is_organization_context')) {
            return response()->json(['message' => 'Not in organization context.'], 400);
        }

        $organization = $request->attributes->get('organization');
        $myRole = $request->attributes->get('organization_role');

        if (!in_array($myRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized to remove members.'], 403);
        }

        $targetMemberRole = $organization->getMemberRole($userId);
        
        if ($targetMemberRole === 'owner') {
            return response()->json(['message' => 'Cannot remove the owner.'], 403);
        }

        // Detach member
        $organization->members()->detach($userId);

        // Revoke active Sanctum tokens immediately
        $user = User::find($userId);
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json(['status' => 'success', 'message' => 'Member removed and sessions revoked.']);
    }
}
