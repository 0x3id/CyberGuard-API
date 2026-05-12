<?php

namespace App\Http\Controllers;

use App\Models\ScanJob;
use App\Models\Target;
use App\Models\Finding;
use App\Services\ScanOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    protected $orchestrator;

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

        // Security / Authorization check
        if ($target->project->user_id !== $request->user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to target',
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

        return response()->json([
            'status' => 'success',
            'message' => 'Scan session initiated successfully.',
            'scan_session_id' => $scanSession->id,
        ]);
    }

    /**
     * Get the status and findings of a scan session.
     */
    public function getScanStatus(Request $request, $scanJobId)
    {
        $scanJob = ScanJob::with('target')->findOrFail($scanJobId);

        // Fetch findings that belong to this scan session
        $findings = Finding::where('scan_job_id', $scanJobId)
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json([
            'status' => 'success',
            'scan_session' => $scanJob,
            'findings' => $findings,
        ]);
    }

    /**
     * Normalized findings as they’re parsed (near real-time)
     */
    public function fetchFindings(Request $request, $scanJobId)
    {
        $scanJob = ScanJob::findOrFail($scanJobId);
        
        if ($scanJob->user_id !== $request->user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to scan job',
            ], 403);
        }
        $findings = Finding::where('scan_job_id', $scanJobId)->get();
        return response()->json([
            'status' => 'success',
            'findings' => $findings,
        ]);
    }
}