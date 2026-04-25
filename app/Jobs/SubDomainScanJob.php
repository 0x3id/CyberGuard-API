<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Models\Finding;

class SubDomainScanJob implements ShouldQueue
{
    use Queueable;



    public $timeout = 1000;
    public $target;
    public $scanSlug;
    /**
     * Create a new job instance.
     */
    public function __construct($target, $scanSlug)
    {
        $this->target = $target;
        $this->scanSlug = $scanSlug;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $result = Process::timeout(1000)->run(['docker', 'run', '--rm', $this->scanSlug, $this->target]);
        Log::info($result->output());
        Log::error($result->errorOutput());
        if($result->successful())
        {
            $output = $result->output();
            // Finding::create([
            //                 'scan_job_id' => "1",
            //                 'target_id' => "2",
            //                 'title' => 'Subdomain Found',
            //                 'description' => "$output",
            //                 'severity' => 'info',
            //                 'status' => 'open',
            //             ]);
        }
        else
        {
            $result->errorOutput();
        }
    }
}
