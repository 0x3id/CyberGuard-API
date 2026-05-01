<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Target;
use App\Models\ProjectCollaborator;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\Rules\ValidDomain;

class TargetController extends Controller
{
    // ────────────────────────────────────────────
    // POST /api/projects/{project}/targets
    // Add New Target To Project
    // ────────────────────────────────────────────
    public function addNewTarget(Request $request, Project $project) : JsonResponse
    {
        $user = $request->user();
        // 1. Check if user have access on this project or no
        if (!$project->hasAccess($user->id)) {
            return response()->json(['status' => 'Error' ,'message' => 'Unauthorized'], 403);
        }

        // 2. Validate data
        $validated = $request->validate([
            'type'        => 'required|in:domain,ip,network',
            'label'       => 'required|string',
            'value' => [
                    'required',
                    Rule::when($request->type === 'ip', ['ip']),
                    Rule::when($request->type === 'domain', ['regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i']), 
                    Rule::when($request->type === 'network', ['regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/']),
            ],
        ]);
        
        // 3. Check if user role is viewer
        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json(['status' => 'Error' ,'message' => 'Only the owner and editors can add targets'], 403);
        }

        // 4. Create Target In Database
        $target = Target::create([
            'project_id' => $project->id,
            'type'       => $request->type,
            'value'      => $request->value,
            'label'      => $request->label,
            'is_verified' => true,
            'risk_score' => 0.00
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'target.add',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status'      => 'Success',
            'message'     => 'Target added successfuly',
            'target'      => $target,
        ], 201);
    }

    // ────────────────────────────────────────────
    // GET /api/targets/{target}
    // Get Target Details
    // ────────────────────────────────────────────
    public function getTargetDetails(Target $target) : JsonResponse
    {
        try {
            $target = Target::where('id' , $target->id)->firstOrFail();
            return response()->json(['target' => $target]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'Error' ,'message' => 'Target not found'], 404);
        }
        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'target.detail',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);
    }

    // ────────────────────────────────────────────
    // Get /api/projects/{project}/targets
    // Get all targets in project
    // ────────────────────────────────────────────
    public function getAllTargets(Request $request, Project $project) : JsonResponse
    {
        // 1. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Get all targets
        $target = Target::all();
        return response()->json(['status' => 'Success' ,'targets' => $target], 200);
    }

    // ────────────────────────────────────────────
    // PATCH /api/projects/{project}/targets/{target}
    // Get all targets in project
    // ────────────────────────────────────────────
    public function updateTarget(Request $request, Project $project, Target $target) : JsonResponse
    {
        $user = $request->user();
        // 1. Check if user have access on this project or no
        if (!$project->hasAccess($user->id)) {
            return response()->json(['status' => 'Error' ,'message' => 'Unauthorized'], 403);
        }

        // 2. Ckeck if user not viewr
        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json(['status' => 'Error' ,'message' => 'Only the project owner & editors can edit'], 403);
        }

        // 3. Ckeck if target is exist
        try {
            $target = Target::where('id' , $target->id)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'Error' ,'message' => 'Target not found'], 404);
        }

        // 4. Validate updated data
        $validated = $request->validate([
            'label'        => 'sometimes|string|max:255',
            'value' => [
                    'required',
                    Rule::when($target->type === 'ip', ['ip']),
                    Rule::when($target->type === 'domain', ['regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i']), 
                    Rule::when($target->type === 'network', ['regex:/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/']),
            ],
        ]);

        // 5. Update in database
        $target->update($validated);

        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => User::class,
            'owner_id'    => $user->id,
            'action'      => 'target.update',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status'      => 'Success',
            'message'     => 'Target updated successfuly',
            'target'      => $target,
        ], 200);
    }


    // ────────────────────────────────────────────
    // DELETE /api/projects/{project}/targets/{target}
    // Delete Specefic Target
    // ────────────────────────────────────────────
    public function deleteTarget(Request $request, Project $project, Target $target): JsonResponse
    {
        $user = $request->user();

        // 1. Check if user have access on this project or no
        if (!$project->hasAccess($user->id)) {
            return response()->json(['status' => 'Error' ,'message' => 'Unauthorized'], 403);
        }

        // 2. Check if this target in db
        try {
            $target = Target::where('id' , $target->id)->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'Error' ,'message' => 'Target not found'], 404);
        }

        // 3. Ckeck if user not viwer
        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json(['status' => 'Error' ,'message' => 'Only the owner and editors can add targets'], 403);
        }

        // 4. Store in database
        $target->delete();

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'target.deleted',
            'entity_type' => Target::class,
            'entity_id'   => $target->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['status' => 'Success' ,'message' => 'Target deleted successfuly'], 200);
    }
}
