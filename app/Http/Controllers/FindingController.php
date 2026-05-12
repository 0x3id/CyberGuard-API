<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Finding;
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

        // Sort by CVSS Score (highest first)
        $findings = $query  ->orderByRaw("FIELD(severity,'critical','high','medium','low','info')")
                            ->get();

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
    // غير حالة الثغرة (resolved, false_positive ...)
    // ────────────────────────────────────────────
    public function updateStatus(Request $request, Finding $finding): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,false_positive',
        ]);

        $finding->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Finding status updated',
            'finding' => $finding,
        ]);
    }
}