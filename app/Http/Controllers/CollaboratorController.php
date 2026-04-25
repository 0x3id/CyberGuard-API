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

class CollaboratorController extends Controller
{
    // ────────────────────────────────────────────
    // POST /api/projects/{project}/invite
    // Invite Person
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
            // email مش required لأن ممكن يبعت Link بدون Email
            'role'  => 'required|in:editor,viewer',
        ]);

        // 4. Create Secret Token
        // Str::random(40) Random Token of 40 char
        $token = Str::random(40);

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

        return response()->json([
            'status' => 'Success',
            'message'     => 'Invitation created',
            'invite_link' => $inviteLink,
            'expires_at'  => $invitation->expires_at,
        ], 201);
    }

    // ────────────────────────────────────────────
    // GET /api/invitations/{token}
    // شوف تفاصيل الدعوة قبل ما تقبل
    // ────────────────────────────────────────────
    public function showInvitation(string $token): JsonResponse
    {
        // جيب الدعوة من الـ Token
        $invitation = ProjectInvitation::where('token', $token)
                                        ->where('status', 'pending')
                                        ->where('expires_at', '>', now())
                                        ->with('project') // جيب بيانات المشروع معاها
                                        ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found or expired',
            ], 404);
        }

        return response()->json([
            'invitation' => [
                'project_name' => $invitation->project->name,
                'role'         => $invitation->role,
                'expires_at'   => $invitation->expires_at,
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

        return response()->json([
            'message' => 'You joined the project successfully',
            'project' => $invitation->project,
        ]);
    }

    // ────────────────────────────────────────────
    // DELETE /api/projects/{project}/collaborators/{user}
    // Delete Person From Project
    // ────────────────────────────────────────────
    public function remove(Request $request, Project $project, User $user): JsonResponse {
        $currentUser = $request->user();

        // 1. بس الـ Owner يقدر يشيل
        if ($project->getUserRole($currentUser->id) !== 'owner') {
            return response()->json(['message' => 'Only the owner can remove members'], 403);
        }

        // 2. الـ Owner مينفعش يشيل نفسه
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot remove yourself'], 400);
        }

        // 3. شيل الـ User من المشروع
        // detach بيحذف السطر من جدول project_collaborators
        $project->collaborators()->detach($user->id);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}