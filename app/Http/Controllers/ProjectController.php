<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCollaborator;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use function Symfony\Component\Clock\now;

class ProjectController extends Controller
{
    // ────────────────────────────────────────────
    // GET /api/projects
    // Get All Projects Of Users
    // ────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Projects That User Owner
        $ownedProjects = Project::where('owner_type', User::class)
                                ->where('owner_id', $user->id)
                                ->withCount('targets') // بيضيف targets_count
                                ->latest()
                                ->get();

        // Projects That User Collaborator
        $collaboratingProjects = $user->collaboratingProjects()
                                ->withCount('targets')
                                ->latest()
                                ->get();

        return response()->json([
            'owned'         => $ownedProjects,
            'collaborating' => $collaboratingProjects,
        ]);
    }

    // ────────────────────────────────────────────
    // POST /api/projects
    // Create New Project
    // ────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. تحقق إن المستخدم يقدر ينشئ Project (الـ Limit)
        // if (!$user->canCreateProject()) {
        //     return response()->json([
        //         'message' => 'You have reached your project limit. Please upgrade your plan.',
        //     ], 403); // 403 = Forbidden
        // }

        // 2. Validate Data From Request
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after:start_date',// after:start_date
        ]);

        // 3. Create The Project
        $project = Project::create([
            ...$validated,
            'owner_type' => User::class,  // App\Models\User
            'owner_id'   => $user->id,
            'created_by' => $user->id,
            'status'     => 'active',
        ]);

        // $project->collaborators()->attach($user->id, [
            //     'role'       => 'owner',
            //     'status'     => 'accepted',
            //     'invited_by' => $user->id,
            //     'invited_at' => now(),
            //     'accepted_at'=> now(),
            // ]);

        // 4. Add User As Owner In project_collaborators
        ProjectCollaborator::create([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'invited_by' => $user->id,
            'role'       => 'owner',
            'status'     => 'accepted',
            'invited_at' => now(),
            'accepted_at'=> now(),
        ]);

        // 5. Audit Log
        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => User::class,
            'owner_id'    => $user->id,
            'action'      => 'project.created',
            'entity_type' => Project::class,
            'entity_id'   => $project->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'project' => $project,
        ], 201);
    }

    // ────────────────────────────────────────────
    // GET /api/projects/{project}
    // Get Specific Project With Details
    // ────────────────────────────────────────────
    public function show(Request $request, Project $project): JsonResponse
    {
        // 1. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. جيب المشروع مع كل علاقاته
        $project->load([
            'targets',           // الأهداف
            'activeCollaborators', // الفريق المقبول
            'creator',           // مين أنشأه
        ]);

        // 3. ضيف إحصائيات
        $project->targets_count   = $project->targets()->count();
        $project->findings_count  = $project->targets()
                                            ->withCount('findings')
                                            ->get()
                                            ->sum('findings_count');

        return response()->json(['project' => $project]);
    }

    // ────────────────────────────────────────────
    // PUT /api/projects/{project}
    // Edit Project
    // ────────────────────────────────────────────
    public function update(Request $request, Project $project): JsonResponse
    {
        // Owner Only can update project
        if ($project->getUserRole($request->user()->id) !== 'owner') {
            return response()->json(['message' => 'Only the project owner can edit'], 403);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,archived,completed',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
        ]);

        $project->update($validated);

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => User::class,
            'owner_id'    => $request->user()->id,
            'action'      => 'project.update',
            'entity_type' => Project::class,
            'entity_id'   => $project->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => $project,
        ]);
    }

    // ────────────────────────────────────────────
    // DELETE /api/projects/{project}
    // Delete Project (Soft Delete)
    // ────────────────────────────────────────────
    public function destroy(Request $request, Project $project): JsonResponse
    {
        // بس الـ Owner يقدر يحذف
        if ($project->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Only the project owner can delete'], 403);
        }

        // Soft Delete — مش بيمسحه فعلاً بس بيحط deleted_at
        $project->delete();

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'project.deleted',
            'entity_type' => Project::class,
            'entity_id'   => $project->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['message' => 'Project deleted successfully']);
    }
}