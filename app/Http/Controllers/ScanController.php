<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ScanJob;
use App\Models\Target;
use App\Models\Project;
use App\Models\User;
use App\Models\Finding;
use App\Models\ProjectCollaborator;
use App\Services\ScanOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    protected ScanOrchestrator $orchestrator;

    public function __construct(ScanOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Get a list of available scanning tools (Pluggable Drivers).
     */
    public function getAvailableScanners()
    {
        $drivers = config('scanners.drivers', []);
        
        // Hide sensitive details from frontend
        $safeDrivers = collect($drivers)->map(function ($config, $id) {
            return [
                'id' => $id,
                'name' => $config['display_name'] ?? $id,
                'category' => $config['category'] ?? 'general',
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'scanners' => $safeDrivers,
        ]);
    }

    /**
     * Start a new orchestrated scan session.
     */
    public function startScan(Request $request)
    {
        $request->validate([
            'target_id' => 'required|exists:targets,id',
            'driver_ids' => 'required|array',
            'driver_ids.*' => 'string', // e.g. ["nmap-tcp-scan", "subdomain-scan"]
        ]);

        $target = Target::findOrFail($request->target_id);

        // Get All Collaborators Of Project
        $collaborators = ProjectCollaborator::where('project_id', $target->project_id)->get();

        // Security / Authorization check
        $exists = $collaborators->contains('user_id', $request->user()->id);
        if (!$exists) 
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to target',
            ], 403);
        }

        $user = $collaborators->where('user_id' , $request->user()->id)->first();
        // Check The Role Of Users
        if ($user->role === 'viewer')
        {
            return response()->json([
                'status' => 'error',
                'message' => 'Only owners and editors can scan',
            ], 403);
        }

        $driverIds = $request->driver_ids; // Tools that will run
        $flags = $request->flags ?? []; // Optional user flags per driver

        //Validate DriversIDs are valid
        $availableDrivers = array_keys(config('scanners.drivers', []));
        $invalidDrivers = array_diff($driverIds, $availableDrivers);

        if (!empty($invalidDrivers)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Some drivers do not exist or are disabled: ' . implode(', ', $invalidDrivers),
            ], 400);
        }
        
        // Initiate the orchestrated scan session
        $scanSession = $this->orchestrator->startScanSession($target, $driverIds, $flags, Auth::id());

        // Get The Project
        $project = Project::where('id', $target->project_id)->first();
        $logs = AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'scan.start',
            'entity_type' => ScanJob::class,
            'entity_id'   => $scanSession->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);


        return response()->json([
            'status' => 'success',
            'message' => 'Scan started successfully.',
            'scan_job' => [
                'id' => $scanSession->id,
                'target_id' => $target->id,
                'driver_id' => $driverIds,
                'status' => $scanSession->status,
                'started_at' => $scanSession->started_at,
            ]
        ]);
    }

    /**
     * Get the status and findings of a scan session.
     */
    public function getScanStatus(Request $request,string $scanJobId)
    {
        $scanJob = ScanJob::with('target')->find($scanJobId);

        // 1. Check If Scan Job Exists
        if (!$scanJob) {
            return response()->json([
                'status' => 'error',
                'message' => 'Scan job not found',
            ], 404);
        }

        // 2. Check If User Have Access To Project
        $project = Project::find($scanJob->project_id);
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to project',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'scan_session' => $scanJob,
        ]);
    }

    /**
     * Normalized findings as they’re parsed (near real-time)
     */
    public function fetchFindings(Request $request,string $scanJobId)
    {
        $scanJob = ScanJob::find($scanJobId);

        // 1. Check If Scan Job Exists
        if (!$scanJob) {
            return response()->json([
                'status' => 'error',
                'message' => 'Scan job not found',
            ], 404);
        }

        // 2. Check If User Have Access To Project
        $project = Project::find($scanJob->project_id);
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to project',
            ], 403);
        }

        // 3. Get All Findings Of Scan Job
        $findings = $scanJob->findings;

        return response()->json([
            'status' => 'success',
            'findings' => $findings->toArray(),
        ]);
    }

    /**
     * Get All Scans Of Project
     */
    public function projectScans(Request $request, Project $project) : JsonResponse
    {

        // 1. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'], 
                403);
        }

        // 2. Get all scans of project
        $scans = ScanJob::where('project_id', $project->id)->get();

        // 3. Get Scans Data with metadata
        $scansData = [];
        foreach($scans as $scan) {
            // Get Target Name
            $targetModel = Target::find($scan->target_id);
            $targetName = $targetModel ? $targetModel->value : null;

            // Get Project Name
            $projectModel = Project::find($scan->project_id);
            $projectName = $projectModel ? $projectModel->name : null;

            // Get User Name (triggered_by)
            $userModel = User::find($scan->triggered_by);
            $userName = $userModel ? $userModel->full_name : null;

            $scansData[] = [
                'scan' => $scan,
                'metadata' => [
                    'target_name' => $targetName,
                    'project_name' => $projectName,
                    'triggered_by' => $userName,
                ]
            ];
        }

        return response()->json([
            'status'=> 'success',
            'scans' => $scansData
        ], 200);

    }

    /**
     * Get All Target Scans
     */
    public function targetScans(Request $request, Target $target) : JsonResponse
    {
        // 1. Get The Project Of Target
        $project = Project::where('id',$target->project_id)->first();

        // 2. Check if user have access in this project
        if (!$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'], 
                403);
        }

        // 3. Get All Scans Of Target
        $scans = ScanJob::where('target_id', $target->id)->get();

        // 4. Get Scans Data with metadata
        $scansData = [];
        foreach($scans as $scan) {
            // Get Target Name
            $targetModel = Target::find($scan->target_id);
            $targetName = $targetModel ? $targetModel->value : null;

            // Get Project Name
            $projectModel = Project::find($scan->project_id);
            $projectName = $projectModel ? $projectModel->name : null;

            // Get User Name (triggered_by)
            $userModel = User::find($scan->triggered_by);
            $userName = $userModel ? $userModel->full_name : null;

            $scansData[] = [
                'scan' => $scan,
                'metadata' => [
                    'target_name' => $targetName,
                    'project_name' => $projectName,
                    'triggered_by' => $userName,
                ]
            ];
        }

        return response()->json([
            'status'=> 'success',
            'scans' => $scansData
        ], 200);

    }

    /**
     * Pause Specific scan by scanJobId
     */
    public function pauseScan(Request $request, string $scanJobId)
    {
        // 1. Get Project and ScanJob
        $scanJob = ScanJob::findOrFail($scanJobId);
        $project = Project::find($scanJob->project_id);

        // 2. Check If user have access to project or no
        if (!$project || !$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to scan job',
            ], 403);
        }

        // 3. Check If User Owner or Editor 
        $role = $project->getUserRole($request->user()->id);
        if ($role === 'viewer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only owners and editors can pause a scan',
            ], 403);
        }

        // 4. Check If Scan Is Running
        if ($scanJob->status !== 'running') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only running scans can be paused',
            ], 400);
        }

        $scanJob->status = 'pending';
        $scanJob->save();

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'scan.pause',
            'entity_type' => ScanJob::class,
            'entity_id'   => $scanJob->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Scan pause signal sent. The background worker will terminate the container within 3 seconds.',
            'scan_job_status' => $scanJob->status,
        ]);
    }

    /**
     * Continue Paused Scan
     */
    public function continueScan(Request $request, string $scanJobId)
    {
        // 1. Get Project and ScanJob
        $scanJob = ScanJob::findOrFail($scanJobId);
        $project = Project::find($scanJob->project_id);

        // 2. Check If user have access to project or no
        if (!$project || !$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to scan job',
            ], 403);
        }

        // 3. Check If User Owner or Editor 
        $role = $project->getUserRole($request->user()->id);
        if ($role === 'viewer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only owners and editors can continue a scan',
            ], 403);
        }

        // 4. Check If Scan Is Paused (pending)
        if ($scanJob->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only paused scans can be continued',
            ], 400);
        }

        // 5. Resume the scan
        $scanJob->status = 'running';
        $scanJob->save();

        // 6. Re-enqueue the drivers
        $target = Target::find($scanJob->target_id);
        if ($scanJob->container_id) {
            $driverIds = explode(',', $scanJob->container_id);
            foreach ($driverIds as $driverId) {
                // We pass empty flags array here as user flags aren't persisted in ScanJob currently,
                // but this will resume the drivers with default flags.
                $this->orchestrator->enqueueDriver($scanJob, $target, trim($driverId));
            }
        }

        // 7. Log the action
        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'scan.continue',
            'entity_type' => ScanJob::class,
            'entity_id'   => $scanJob->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Scan continued successfully.',
            'scan_job' => $scanJob,
        ]);
    }

    /**
     * Cancel Specific Scan
     */
    public function cancelScan(Request $request, string $scanJobId)
    {
        // 1. Get Project and ScanJob
        $scanJob = ScanJob::findOrFail($scanJobId);
        $project = Project::find($scanJob->project_id);

        // 2. Check If user have access to project or no
        if (!$project || !$project->hasAccess($request->user()->id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to scan job',
            ], 403);
        }

        // 3. Check If User Owner or Editor 
        $role = $project->getUserRole($request->user()->id);
        if ($role === 'viewer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only owners and editors can cancel a scan',
            ], 403);
        }

        // 4. Check if Scan is active (running or pending)
        if (in_array($scanJob->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'This scan cannot be cancelled because it is already ' . $scanJob->status,
            ], 400);
        }

        // 5. Cancel the scan
        $scanJob->status = 'cancelled';
        $scanJob->finished_at = now();
        $scanJob->save();

        // 6. Log the action
        AuditLog::create([
            'user_id'     => $request->user()->id,
            'owner_type'  => $project->owner_type,
            'owner_id'    => $project->owner_id,
            'action'      => 'scan.cancel',
            'entity_type' => ScanJob::class,
            'entity_id'   => $scanJob->id,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Scan cancelled successfully.',
            'scan_job' => $scanJob,
        ]);
    }
}