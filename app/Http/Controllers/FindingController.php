<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Finding;
use App\Models\Project;
use App\Models\ScanJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class FindingController extends Controller
{
    // ────────────────────────────────────────────
    // GET /api/targets/{target}/findings
    // Get All Findings about Target with Filters
    // ────────────────────────────────────────────
    public function index(Request $request, Target $target): JsonResponse
    {
        if (!$target->project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Quary To Filtring
        $query = $target->findings();

        // Filter by severity → /findings?severity=critical
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by status → /findings?status=open
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by tool used
        if ($request->has('tool')) {
            $query->where('driver_id', $request->tool);
        }


        // Sort by CVSS Score (highest first)
        $findings = $query  ->orderByRaw("FIELD(severity,'critical','high','medium','low','info')")
                            ->paginate(20);

        return response()->json(['findings' => $findings]);
    }

    // ────────────────────────────────────────────
    // GET /projects/{project}/findings
    // Get All Findings of project with filters
    // ────────────────────────────────────────────
    public function getProjectFindings(Request $request, Project $project): JsonResponse
    {
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Quary To Filtring
        $query = $project->findings();

        // Filter by severity → /findings?severity=critical
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        // Filter by status → /findings?status=open
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by tool used
        if ($request->has('tool')) {
            $query->where('driver_id', $request->tool);
        }

        // Filter by target
        if ($request->has('target')) {
            $query->where('target_id', $request->target);
        }


        // Sort by CVSS Score (highest first)
        $findings = $query  ->orderByRaw("FIELD(severity,'critical','high','medium','low','info')")
                            ->paginate(20);

        return response()->json(['findings' => $findings]);
    }


    // ────────────────────────────────────────────
    // GET /api/targets/{target}/endpoints
    // Get Endpoints related to Target with Status if target has endpoints
    // ────────────────────────────────────────────
    public function getEndpoints(Request $request, Target $target): JsonResponse
    {
        if (!$target->project->hasAccess($request->user()->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if target is domain
        if ($target->type !== 'domain') {
            return response()->json(['message' => 'Not Found Endpoints For This Target'], 400);
        }
        $endpoints = Finding::where('target_id', $target->id)
                        ->where('driver_id', 'web-endpoint-fuzzer')
                        ->pluck('metadata')
                        ->flatMap(fn ($meta) => $meta['urls'] ?? [])
                        ->unique()
                        ->values()
                        ->toArray();
        
        return response()->json(['endpoints' => $endpoints]);
    }

    // ────────────────────────────────────────────
    // PATCH /api/findings/{finding}/status
    // Update Finding Status (resolved, false_positive ...)
    // ────────────────────────────────────────────
    public function updateStatus(Request $request, Finding $finding): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,false_positive',
        ]);

        $target = Target::findOrFail($finding->target_id);
        $project = Project::findOrFail($target->project_id);
        $user = $request->user();

        if (!$project->hasAccess($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json(['message' => 'Only the project owner & editors can update finding status'], 403);
        }

        $finding->update(['status' => $validated['status']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Finding status updated',
            'finding' => $finding,
        ]);
    }

    // ────────────────────────────────────────────
    // PATCH /api/findings/{finding}/severity
    // Update Finding Severity ('critical', 'high', 'medium', 'low', 'info')
    // ────────────────────────────────────────────
    public function updateSeverity(Request $request, Finding $finding): JsonResponse
    {
        $validated = $request->validate([
            'severity' => 'required|in:critical,high,medium,low,info',
        ]);

        $user = $request->user();
        $target = Target::findOrFail($finding->target_id);
        $project = Project::findOrFail($target->project_id);

        // 1. Check If User Have Acces On Project
        if (!$project->hasAccess($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Check if the user in owner or editor
        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json(['message' => 'Only the project owner & editors can update finding status'], 403);
        }

        $finding->update(['severity' => $validated['severity']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Finding status updated',
            'finding' => $finding,
        ]);

    }

    // ────────────────────────────────────────────
    // POST /api /targets/{target}/findings
    // Upload New Finding
    // ────────────────────────────────────────────
    public function uploadFinding(Request $request, Target $target): JsonResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($target->project_id);

        // 1. Check If User Have Access On Project
        if (!$project->hasAccess($user->id)) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // 2. Check if user is owner/editor
        if ($project->getUserRole($user->id) === 'viewer') {
            return response()->json([
                'message' => 'Only project owner & editors can upload findings'
            ], 403);
        }

        // 3. Validate Data
        $validated = $request->validate([

            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string'],

            'severity'      => ['required', 'in:critical,high,medium,low,info'],
            'cvss_score'    => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cvss_vector'   => ['nullable', 'string', 'max:255'],
            'cve_id'        => ['nullable', 'string', 'max:100'],

            'remediation'   => ['nullable', 'string'],
            'status'        => ['nullable', 'in:open,in_progress,resolved,false_positive'],

            'affected_url'  => ['nullable', 'url'],
            'proof'         => ['nullable', 'string'],

            'metadata'      => ['nullable', 'array'],
            'tags'          => ['nullable', 'array'],
            'tags.*'        => ['string', 'max:100'],
        ]);

        // 6. Create Scan Job
        $scanJob = ScanJob::create([
            'target_id'   => $target->id,
            'project_id'  => $project->id,
            'triggered_by'=> $user->id,
            'scan_type'   => 'auto',
            'status'      => 'completed',
            'started_at'  => now(),
            'finished_at' => now(),
        ]);

        // 5. Create Finding
        $finding = Finding::create([
            'scan_job_id'  => $scanJob->id,
            'target_id'    => $target->id,
            'driver_id'    => $validated['driver_id'] ?? 'N/A',

            'title'        => $validated['title'],
            'description'  => $validated['description'],

            'severity'     => $validated['severity'],
            'cvss_score'   => $validated['cvss_score'] ?? 'N/A',
            'cvss_vector'  => $validated['cvss_vector'] ?? 'N/A',
            'cve_id'       => $validated['cve_id'] ?? 'N/A',

            'remediation'  => $validated['remediation'] ?? 'N/A',
            'status'       => $validated['status'] ?? 'open',

            'affected_url' => $validated['affected_url'] ?? 'N/A',
            'proof'        => $validated['proof'] ?? 'N/A',

            'metadata'     => $validated['metadata'] ?? [],
            'tags'         => $validated['tags'] ?? [],

            'raw_data'     => $validated['raw_data'] ?? 'N/A',
        ]);

        // 5. Response
        return response()->json([
            'status'  => 'success',
            'message' => 'Finding uploaded successfully',
            'finding' => $finding,
        ], 201);
    }


}