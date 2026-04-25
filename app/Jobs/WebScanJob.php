<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ScanJob;
use App\Models\Finding;
use Illuminate\Support\Facades\Process;

class WebScanJob implements ShouldQueue
{
    use Queueable;

    protected $scanJobId;

    /**
     * Create a new job instance.
     */
    public function __construct($scanJobId)
    {
        $this->scanJobId = $scanJobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scanJob = ScanJob::find($this->scanJobId);
        if (!$scanJob) {
            return;
        }

        $target = $scanJob->target;
        if (!$target) {
            return;
        }

        $domain = $target->value;

        $scanJob->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = Process::run(['docker', 'run', '--rm', 'sub-domain-scan', $domain]);

            if ($result->successful()) {
                $output = $result->output();
                $subdomains = explode("\n", trim($output));

                foreach ($subdomains as $subdomain) {
                    $subdomain = trim($subdomain);
                    if (!empty($subdomain)) {
                        Finding::create([
                            'scan_job_id' => $this->scanJobId,
                            'target_id' => $target->id,
                            'title' => 'Subdomain Found',
                            'description' => "Subdomain discovered: {$subdomain}",
                            'severity' => 'info',
                            'status' => 'open',
                        ]);
                    }
                }

                $scanJob->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);
            } else {
                $scanJob->update([
                    'status' => 'failed',
                    'error_log' => $result->errorOutput(),
                    'finished_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $scanJob->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
