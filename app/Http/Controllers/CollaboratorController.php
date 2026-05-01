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

class CollaboratorController extends Controller
{


    // ────────────────────────────────────────────
    // PATCH /api/projects/{project}/collaborators/{user}
    // Change User Role
    // ────────────────────────────────────────────
    public function changeRole(Request $request, Project $project, User $user) : JsonResponse {
        $currentUser = $request->user();

        // 1. Only Owner can change role
        if ($project->getUserRole($currentUser->id) !== 'owner') {
            return response()->json(['message' => 'Only the owner can change role'], 403);
        }

        // 2. Change User Role
        $project->collaborators()->updateExistingPivot($user->id, ['role' => $request->role]);

        AuditLog::create([
            'user_id'     => $currentUser->id,
            'action'      => 'collaborator.changeRole',
            'entity_type' => ProjectCollaborator::class,
            'entity_id'   => $user->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);


        return response()->json([
            'message' => 'Role changed successfully',
        ], 200);
    }

    public function getAll(Request $request, Project $project): JsonResponse
    {
        $currentUser = $request->user();

        // 1. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json(['status'=>'Error','message' => 'Unauthorized'], 403);
        }


        $collaborators = ProjectCollaborator::where('project_id', $project->id)
            ->with(['user:id,full_name,email'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 'Success',
            'collaborators' => $collaborators,
        ]);
    }


    //_____________________________________________
    // DELETE /api/projects/{project}/collaborators/{user}
    // Delete Person From Project
    //_____________________________________________
    public function remove(Request $request, Project $project, User $user): JsonResponse {
        $currentUser = $request->user();

        // 1. Only Owner can remove members
        if ($project->getUserRole($currentUser->id) !== 'owner') {
            return response()->json(['message' => 'Only the owner can remove members'], 403);
        }

        // 2. Owner cannot remove themselves
        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot remove yourself'], 400);
        }

        // 3. Remove User From Project
        // detach will delete the row from project_collaborators table
        $project->collaborators()->detach($user->id);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}