<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Target;
use App\Models\Finding;
use App\Models\ProjectCollaborator;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    // ────────────────────────────────────────────
    // GET /api/projects
    // Get All Projects
    // ────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->attributes->get('is_organization_context')) {
            $organization = $request->attributes->get('organization');

            $projects = Project::where('owner_type', Organization::class)
                                ->where('owner_id', $organization->id)
                                ->withCount('targets')
                                ->latest()
                                ->get();

            foreach ($projects as $project) {
                $this->calcRiskScore($project);
            }

            return response()->json([
                'status' => 'success',
                'projects' => $projects,
            ]);
        }

        // Personal Workspace
        $ownedProjects = Project::where('owner_type', User::class)
                                ->where('owner_id', $user->id)
                                ->withCount('targets')
                                ->latest()
                                ->get();

        $collaboratingProjects = $user->collaboratingProjects()
                                ->withCount('targets')
                                ->latest()
                                ->get();

        foreach($ownedProjects as $ownedProject) {
            $this->calcRiskScore($ownedProject);
        }
        foreach($collaboratingProjects as $collaboratingProject) {
            $this->calcRiskScore($collaboratingProject);
        }

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
        Gate::authorize('create', Project::class);

        $user = $request->user();
        $isOrgContext = $request->attributes->get('is_organization_context');
        $organization = $request->attributes->get('organization');

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after:start_date',
        ]);

        $project = Project::create([
            ...$validated,
            'owner_type' => $isOrgContext ? Organization::class : User::class,
            'owner_id'   => $isOrgContext ? $organization->id : $user->id,
            'created_by' => $user->id,
            'status'     => 'active',
        ]);

        if (!$isOrgContext) {
            ProjectCollaborator::create([
                'project_id' => $project->id,
                'user_id'    => $user->id,
                'invited_by' => $user->id,
                'role'       => 'owner',
                'status'     => 'accepted',
                'invited_at' => now(),
                'accepted_at'=> now(),
            ]);
        }

        AuditLog::create([
            'user_id'     => $user->id,
            'owner_type'  => $isOrgContext ? Organization::class : User::class,
            'owner_id'    => $isOrgContext ? $organization->id : $user->id,
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
        Gate::authorize('view', $project);

        $project->load([
            'targets',
            'activeCollaborators',
            'creator',
        ]);

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
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:active,archived,completed',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
        ]);

        $project->update($validated);

        $isOrgContext = $request->attributes->get('is_organization_context');
        $organization = $request->attributes->get('organization');

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $isOrgContext ? Organization::class : User::class,
            'owner_id'    => $isOrgContext ? $organization->id : $request->user()->id,
            'action'      => 'project.updated',
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
        Gate::authorize('delete', $project);

        $project->delete();

        $isOrgContext = $request->attributes->get('is_organization_context');
        $organization = $request->attributes->get('organization');

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $isOrgContext ? Organization::class : User::class,
            'owner_id'    => $isOrgContext ? $organization->id : $request->user()->id,
            'action'      => 'project.deleted',
            'entity_type' => Project::class,
            'entity_id'   => $project->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Calc Risk Score Of Project based on formula
     * Score = (Critical * 10 + high * 7 + medium * 4 + low * 1) / 100
     */
    private function calcRiskScore(Project $project): void
    {
        $targetIds = Target::where('project_id', $project->id)->pluck('id');
        $findings = Finding::whereIn('target_id', $targetIds)->get();

        if ($findings->isEmpty()) {
            $project->risk_score = 0.0;
            $project->save();
            return;
        }

        $critical = $findings->where('severity', 'critical')->count();
        $high     = $findings->where('severity', 'high')->count();
        $medium   = $findings->where('severity', 'medium')->count();
        $low      = $findings->where('severity', 'low')->count();

        $riskScore = (($critical * 10) + ($high * 7) + ($medium * 4) + ($low * 1));
        $riskScore = ($riskScore /  100);

        $project->risk_score = round($riskScore, 2);
        $project->save();
    }
}