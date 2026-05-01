<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCollaborator;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\AuditLog;

class ProjectInvitationController extends Controller
{
    // ────────────────────────────────────────────
    // POST /api/projects/{project}/invite
    // Create Invitation URL
    // ────────────────────────────────────────────
    public function invite(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // 1. Owner Only can send
        if ($project->getUserRole($user->id) !== 'owner') {
            return response()->json(['status' => 'Error' ,'message' => 'Only the owner can invite'], 403);
        }

        // 2. Check of limit
        // if (!$project->canAddCollaborator()) {
        //     return response()->json([
        //         'message' => 'Collaborator limit reached. Upgrade your plan.',
        //     ], 403);
        // }

        // 3. Validate Data
        $validated = $request->validate([
            'email' => 'nullable|email',
            'role'  => 'required|in:editor,viewer',
        ]);

        // 4. Create Secret Token
        // Str::random(40) Random Token of 40 char
        $token = Str::random(64);

        // 5. Store Invitation In Database
        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'invited_by' => $user->id,
            'email'      => $validated['email'] ?? null,
            'token'      => $token,
            'role'       => $validated['role'],
            'status'     => 'pending',
            'expires_at' => now()->addDays(7), // Expire in 7 days
        ]);

        // 6. لو في Email → ابعت إيميل
        // if ($validated['email'] ?? null) {
        //     // هنا بتبعت الإيميل
        //     // Mail::to($validated['email'])->send(new ProjectInvitationMail($invitation));
        // }

        // 7. Return Link To User
        $inviteLink = env('FRONTEND_URL') . 'invite/' . $token;

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'invitation.creat',
            'entity_type' => ProjectInvitation::class,
            'entity_id'   => $invitation->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status' => 'Success',
            'message'     => 'Invitation created',
            'invite_link' => $inviteLink,
            'expires_at'  => $invitation->expires_at,
        ], 201);
    }

    // ────────────────────────────────────────────
    // GET /api/invitations/{token}
    // Show Invitation Details
    // ────────────────────────────────────────────
    public function showInvitation(string $token): JsonResponse
    {
        // Get token of invitation
        $invitation = ProjectInvitation::where('token', $token)
                                        ->where('status', 'pending')
                                        ->where('expires_at', '>', now())
                                        ->with('project') // Get Project Detail
                                        ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found or expired',
            ], 404);
        }
        $invited_by = User::find($invitation->invited_by);
        return response()->json([
            'invitation' => [
                'project_name' => $invitation->project->name,
                'role'         => $invitation->role,
                'expires_at'   => $invitation->expires_at,
                'invited_by'   => $invited_by->full_name,
                'job_tittle'   => $invited_by->job_tittle,
                'avatar_url'   => $invited_by->avatar_url,
            ],
        ]);
    }

    // ────────────────────────────────────────────
    // POST /api/invitations/{token}/accept
    // Accept Invitation
    // ────────────────────────────────────────────
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        // 1. Get Invitation
        $invitation = ProjectInvitation::where('token', $token)
                                        ->where('status', 'pending')
                                        ->where('expires_at', '>', now())
                                        ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid or expired invitation'], 404);
        }

        // 2. User Is Exist in project or no??
        $alreadyMember = $invitation->project
                                    ->collaborators()
                                    ->where('user_id', $user->id)
                                    ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member'], 409);
            // 409 = Conflict
        }

        // 3. Add User To Project
        $invitation->project->collaborators()->attach($user->id, [
            'id' => Str::uuid(),
            'role'        => $invitation->role,
            'status'      => 'accepted',
            'invited_by'  => $invitation->invited_by,
            'invited_at'  => $invitation->created_at,
            'accepted_at' => now(),
        ]);

        // 4. Change status accepted
        $invitation->update(['status' => 'accepted']);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'invitation.accept',
            'entity_type' => ProjectInvitation::class,
            'entity_id'   => $invitation->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'message' => 'You joined the project successfully',
            'project' => $invitation->project,
        ]);
    }

    // ────────────────────────────────────────────
    // DELETE /api/invitations/{token}/reject
    // Accept Invitation
    // ────────────────────────────────────────────
    public function reject(Request $request, string $token)
    {
        $user = $request->user();

        // 1. Get Invitation
        $invitation = ProjectInvitation::where('token', $token)
                                        ->where('status', 'pending')
                                        ->where('expires_at', '>', now())
                                        ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invalid or expired invitation'], 404);
        }

        // 2. User Is Exist in project or no??
        $alreadyMember = $invitation->project
                                    ->collaborators()
                                    ->where('user_id', $user->id)
                                    ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member'], 409);
            // 409 = Conflict
        }

        // 3. Add User To Project
        // $invitation->project->collaborators()->attach($user->id, [
        //     'id' => Str::uuid(),
        //     'role'        => $invitation->role,
        //     'status'      => 'accepted',
        //     'invited_by'  => $invitation->invited_by,
        //     'invited_at'  => $invitation->created_at,
        //     'accepted_at' => now(),
        // ]);

        // 4. Change status accepted
        $invitation->update(['status' => 'expired']);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'invitation.rejected',
            'entity_type' => ProjectInvitation::class,
            'entity_id'   => $invitation->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status' => 'Success',
            'message' => 'You rejected the invitation successfully'
        ]);
    }
}
