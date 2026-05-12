<?php

namespace App\Services;

use App\Models\ScanJob;
use App\Models\Target;
use App\Jobs\ExecuteScanDriverJob;
use Illuminate\Support\Facades\Log;

class ScanOrchestrator
{
    /**
     * Start a scan session with a list of drivers.
     */
    public function startScanSession(Target $target, array $driverIds, array $userFlags = [], $userId = null)
    {
        // Create the generic ScanJob representation (Scan Session)
        $scanJob = ScanJob::create([
            'target_id' => $target->id,
            'project_id' => $target->project_id ?? null,
            'triggered_by' => $userId,
            'scan_type' => 'targeted',
            'container_id' => implode(',', $driverIds),
            'status' => 'running',
            'started_at' => now(),
        ]);

        foreach ($driverIds as $driverId) {
            $this->enqueueDriver($scanJob, $target, $driverId, $userFlags[$driverId] ?? []);
        }

        return $scanJob;
    }

    /**
     * Enqueue a specific driver job.
     */
    public function enqueueDriver(ScanJob $scanSession, Target $target, string $driverId, array $flags = [])
    {
        $driverConfig = config("scanners.drivers.{$driverId}");

        if (!$driverConfig) {
            Log::error("Driver {$driverId} not found in Tool Registry.");
            return;
        }

        // Dispatch the job to the queue
        ExecuteScanDriverJob::dispatch($scanSession, $target, $driverId, $flags);
    }

    /**
     * Evaluate triggers after a job completes.
     */
    public function evaluateTriggers(ScanJob $scanSession, Target $target, string $driverId, array $findings)
    {
        $driverConfig = config("scanners.drivers.{$driverId}");

        if (empty($driverConfig['triggers'])) {
            return;
        }

        foreach ($driverConfig['triggers'] as $trigger) {
            $condition = $trigger['condition'];
            $triggeredDriver = $trigger['trigger_driver'];

            foreach ($findings as $finding) {
                if ($this->evaluateCondition($condition, $finding)) {
                    Log::info("Trigger matched for finding! Enqueuing driver: {$triggeredDriver}");
                    $this->enqueueDriver($scanSession, $target, $triggeredDriver);
                    break; // Only trigger once per condition per driver
                }
            }
        }
    }

    /**
     * Basic expression evaluator for trigger conditions.
     */
    private function evaluateCondition(string $condition, \App\Models\Finding $finding): bool
    {
        // A very simple regex matcher for "finding.port == 80 || finding.port == 443" style conditions.
        // For a full implementation, you'd use a real expression parser like Symfony ExpressionLanguage.
        // Here we just hardcode simple parsing for demonstration purposes of the architecture.
        $metadata = $finding->metadata ?? [];
        
        if (str_contains($condition, 'finding.port == 80 || finding.port == 443')) {
            $port = $metadata['port'] ?? null;
            return $port == 80 || $port == 443;
        }

        return false;
    }
}
