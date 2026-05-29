<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Project;
use App\Models\ScanJob;
use App\Models\Target;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/metrics
     *
     * Aggregates and returns all key security metrics for the
     * Risk Management Dashboard in a structured JSON payload.
     *
     * Optional query param:
     *   ?project_id={uuid}  → scope all metrics to one project
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ── 1. Resolve authorized project scope ───────────────────────────
            //
            // If a specific project is requested, validate access.
            // Otherwise, aggregate across ALL projects the user can access
            // (owned + actively collaborating).

            if ($request->filled('project_id')) {
                $project = Project::find($request->project_id);

                if (!$project || !$project->hasAccess($user->id)) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Project not found or access denied.',
                    ], 403);
                }

                $authorizedProjectIds = [$project->id];
            } else {
                // IDs of projects the user owns
                $ownedIds = Project::where('created_by', $user->id)
                    ->pluck('id');

                // IDs of projects the user actively collaborates on
                $collaboratingIds = $user->collaboratingProjects()
                    ->pluck('projects.id');

                $authorizedProjectIds = $ownedIds
                    ->merge($collaboratingIds)
                    ->unique()
                    ->values()
                    ->all();
            }

            if (empty($authorizedProjectIds)) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'No authorized projects found for this account.',
                    'data'    => $this->emptyPayload(),
                ]);
            }

            // ── 2. Resolve all target IDs within authorized projects ───────────
            //
            // A single query; results are used repeatedly below to scope
            // all findings/scan queries — avoids repeated sub-selects.

            $authorizedTargetIds = Target::whereIn('project_id', $authorizedProjectIds)
                ->pluck('id')
                ->all();

            // ── 3. Finding counts via a single aggregation query ───────────────
            //
            // One GROUP BY query replaces N separate count() calls.
            // Indexed on (target_id, severity, status) → see migration.

            $findingAggregates = DB::table('findings')
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count"),
                    DB::raw("SUM(CASE WHEN severity = 'high'     THEN 1 ELSE 0 END) as high_count"),
                    DB::raw("SUM(CASE WHEN severity = 'medium'   THEN 1 ELSE 0 END) as medium_count"),
                    DB::raw("SUM(CASE WHEN severity = 'low'      THEN 1 ELSE 0 END) as low_count"),
                    DB::raw("SUM(CASE WHEN severity = 'info'     THEN 1 ELSE 0 END) as info_count"),
                    DB::raw("SUM(CASE WHEN status IN ('resolved','false_positive') THEN 1 ELSE 0 END) as resolved_count"),
                    DB::raw("SUM(CASE WHEN status = 'open'        THEN 1 ELSE 0 END) as open_count"),
                    DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count"),
                ])
                ->whereIn('target_id', $authorizedTargetIds)
                ->first();

            $totalFindings    = (int) ($findingAggregates->total           ?? 0);
            $criticalCount    = (int) ($findingAggregates->critical_count  ?? 0);
            $highCount        = (int) ($findingAggregates->high_count      ?? 0);
            $mediumCount      = (int) ($findingAggregates->medium_count    ?? 0);
            $lowCount         = (int) ($findingAggregates->low_count       ?? 0);
            $infoCount        = (int) ($findingAggregates->info_count      ?? 0);
            $resolvedCount    = (int) ($findingAggregates->resolved_count  ?? 0);
            $openCount        = (int) ($findingAggregates->open_count      ?? 0);
            $inProgressCount  = (int) ($findingAggregates->in_progress_count ?? 0);

            // ── 4. Global Risk Score ───────────────────────────────────────────
            //
            // Weighted formula (same as calcRiskScore on Target):
            //   Score = (Critical×10 + High×7 + Medium×4 + Low×1) / 100
            //
            // Also surface the mathematical average of stored per-target scores.

            $globalRiskScore = round(
                (($criticalCount * 10) + ($highCount * 7) + ($mediumCount * 4) + ($lowCount * 1)) / 100,
                2
            );

            $averageTargetRiskScore = Target::whereIn('id', $authorizedTargetIds)
                ->avg('risk_score') ?? 0.0;
            $averageTargetRiskScore = round((float) $averageTargetRiskScore, 2);

            // ── 5. Target & Scan counts ────────────────────────────────────────

            $totalTargets = count($authorizedTargetIds);

            $totalScans = ScanJob::whereIn('project_id', $authorizedProjectIds)
                ->count();

            // ── 6. Active scans ────────────────────────────────────────────────
            //
            // Eager-load `target` and `triggeredBy` to avoid N+1.
            // We expose only safe metadata fields — no internal error logs.

            $activeScans = ScanJob::with(['target:id,value,type,label', 'triggeredBy:id,full_name'])
                ->whereIn('project_id', $authorizedProjectIds)
                ->where('status', 'running')
                ->orderByDesc('started_at')
                ->get()
                ->map(fn (ScanJob $job) => [
                    'scan_job_id' => $job->id,
                    'scan_type'   => $job->scan_type,
                    'status'      => $job->status,
                    'started_at'  => $job->started_at?->toIso8601String(),
                    'duration'    => $job->duration,
                    'target'      => $job->target ? [
                        'id'    => $job->target->id,
                        'label' => $job->target->label,
                        'value' => $job->target->value,
                        'type'  => $job->target->type,
                    ] : null,
                    'triggered_by' => $job->triggeredBy ? [
                        'id'        => $job->triggeredBy->id,
                        'full_name' => $job->triggeredBy->full_name,
                    ] : null,
                ]);

            // ── 7. Recent Findings ─────────────────────────────────────────────
            //
            // Configurable limit via ?recent_limit=N (default 10, max 50).
            // Eager-load `target` to avoid N+1.

            $recentLimit = min((int) ($request->input('recent_limit', 10)), 50);

            $recentFindings = Finding::with(['target:id,value,type,label'])
                ->whereIn('target_id', $authorizedTargetIds)
                ->orderByDesc('created_at')
                ->limit($recentLimit)
                ->get()
                ->map(fn (Finding $finding) => [
                    'id'           => $finding->id,
                    'title'        => $finding->title,
                    'severity'     => $finding->severity,
                    'severity_color' => $finding->severity_color,
                    'status'       => $finding->status,
                    'cvss_score'   => $finding->cvss_score,
                    'cve_id'       => $finding->cve_id,
                    'affected_url' => $finding->affected_url,
                    'discovered_at' => $finding->created_at?->toIso8601String(),
                    'target' => $finding->target ? [
                        'id'    => $finding->target->id,
                        'label' => $finding->target->label,
                        'value' => $finding->target->value,
                        'type'  => $finding->target->type,
                    ] : null,
                ]);

            // ── 8. Build final response payload ───────────────────────────────

            return response()->json([
                'status'    => 'success',
                'generated_at' => now()->toIso8601String(),
                'scope' => [
                    'project_id'  => $request->input('project_id'),
                    'project_ids' => $authorizedProjectIds,
                ],
                'data' => [

                    // ── Findings Summary ──────────────────────────────────────
                    'findings_summary' => [
                        'total'       => $totalFindings,
                        'critical'    => $criticalCount,
                        'open'        => $openCount,
                        'in_progress' => $inProgressCount,
                        'resolved'    => $resolvedCount,
                    ],

                    // ── Findings By Severity Distribution ────────────────────
                    'findings_by_severity' => [
                        'critical' => [
                            'count'      => $criticalCount,
                            'percentage' => $totalFindings > 0
                                ? round(($criticalCount / $totalFindings) * 100, 1)
                                : 0,
                        ],
                        'high' => [
                            'count'      => $highCount,
                            'percentage' => $totalFindings > 0
                                ? round(($highCount / $totalFindings) * 100, 1)
                                : 0,
                        ],
                        'medium' => [
                            'count'      => $mediumCount,
                            'percentage' => $totalFindings > 0
                                ? round(($mediumCount / $totalFindings) * 100, 1)
                                : 0,
                        ],
                        'low' => [
                            'count'      => $lowCount,
                            'percentage' => $totalFindings > 0
                                ? round(($lowCount / $totalFindings) * 100, 1)
                                : 0,
                        ],
                        'info' => [
                            'count'      => $infoCount,
                            'percentage' => $totalFindings > 0
                                ? round(($infoCount / $totalFindings) * 100, 1)
                                : 0,
                        ],
                    ],

                    // ── Risk Score ────────────────────────────────────────────
                    'risk_score' => [
                        'global_score'         => $globalRiskScore,
                        'average_target_score' => $averageTargetRiskScore,
                        'formula'              => 'Score = (Critical×10 + High×7 + Medium×4 + Low×1) / 100',
                        'risk_level'           => $this->resolveRiskLevel($globalRiskScore),
                    ],

                    // ── Assets & Scan History ─────────────────────────────────
                    'infrastructure' => [
                        'total_targets' => $totalTargets,
                        'total_scans'   => $totalScans,
                    ],

                    // ── Active Scans (real-time) ──────────────────────────────
                    'active_scans' => [
                        'count' => $activeScans->count(),
                        'scans' => $activeScans,
                    ],

                    // ── Recent Findings ───────────────────────────────────────
                    'recent_findings' => [
                        'limit'    => $recentLimit,
                        'count'    => $recentFindings->count(),
                        'findings' => $recentFindings,
                    ],
                ],
            ]);

        } catch (Throwable $e) {
            // Never expose raw exception details or stack traces.
            report($e); // Sends to Laravel's configured log channel.

            return response()->json([
                'status'  => 'error',
                'message' => 'An unexpected error occurred while fetching dashboard metrics. Please try again later.',
            ], 500);
        }
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Resolve a human-readable risk level from a numeric global risk score.
     */
    private function resolveRiskLevel(float $score): string
    {
        return match (true) {
            $score >= 10.0 => 'Critical',
            $score >= 7.0  => 'High',
            $score >= 4.0  => 'Medium',
            $score >= 1.0  => 'Low',
            default        => 'Minimal',
        };
    }

    /**
     * Returns a zero-value payload for users with no authorized projects.
     */
    private function emptyPayload(): array
    {
        return [
            'findings_summary'     => ['total' => 0, 'critical' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0],
            'findings_by_severity' => [
                'critical' => ['count' => 0, 'percentage' => 0],
                'high'     => ['count' => 0, 'percentage' => 0],
                'medium'   => ['count' => 0, 'percentage' => 0],
                'low'      => ['count' => 0, 'percentage' => 0],
                'info'     => ['count' => 0, 'percentage' => 0],
            ],
            'risk_score'      => ['global_score' => 0, 'average_target_score' => 0, 'formula' => 'Score = (Critical×10 + High×7 + Medium×4 + Low×1) / 100', 'risk_level' => 'Minimal'],
            'infrastructure'  => ['total_targets' => 0, 'total_scans' => 0],
            'active_scans'    => ['count' => 0, 'scans' => []],
            'recent_findings' => ['limit' => 10, 'count' => 0, 'findings' => []],
        ];
    }
}
