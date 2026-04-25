<?php

namespace App\Http\Controllers;

use App\Jobs\SubDomainScanJob;
use App\Jobs\WebScanJob;
use App\Models\ScanJob;
use App\Models\Target;
use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function startScan(Request $request)
    {
        // $request->validate([
        //     'target_id' => 'required|exists:targets,id',
        // ]);

        // $target = Target::find($request->target_id);

        // // Check if user has access to the target (assuming targets belong to projects, and user is collaborator)
        // // For simplicity, assume authenticated user can scan any target for now

        // $scanJob = ScanJob::create([
        //     'target_id' => $target->id,
        //     'project_id' => $target->project_id,
        //     'triggered_by' => Auth::id(),
        //     'scan_type' => 'web',
        //     'status' => 'queued',
        // ]);

        // // Dispatch the job
        // WebScanJob::dispatch($scanJob->id);

        // return response()->json([
        //     'status' => 'success',
        //     'message' => 'Scan started',
        //     'scan_job_id' => $scanJob->id,
        // ]);

        $target = $request->target;
        $scanSlug = $request->scanSlug;
        SubDomainScanJob::dispatch($target , $scanSlug);
        return response()->json([
            'status' => 'success',
            'message' => 'Scan started...',
        ]);
    }

    public function getScanStatus(Request $request, $scanJobId)
    {
        $scanJob = ScanJob::with('target')->findOrFail($scanJobId);

        // Check permissions

        $findings = Finding::where('scan_job_id', $scanJobId)->get();

        return response()->json([
            'status' => 'success',
            'scan_job' => $scanJob,
            'findings' => $findings,
        ]);
    }
}