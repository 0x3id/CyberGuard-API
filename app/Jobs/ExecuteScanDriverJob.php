<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Models\ScanJob;
use App\Models\Target;
use App\Services\OutputNormalizer;
use App\Services\ScanOrchestrator;

class ExecuteScanDriverJob implements ShouldQueue
{
    use Queueable;

    public $timeout;
    public $scanSession;
    public $target;
    public $driverId;
    public $userFlags;

    public function __construct(ScanJob $scanSession, Target $target, string $driverId, array $userFlags = [])
    {
        $this->scanSession = $scanSession;
        $this->target = $target;
        $this->driverId = $driverId;
        $this->userFlags = $userFlags;

        $driverConfig = config("scanners.drivers.{$this->driverId}");
        $this->timeout = $driverConfig['timeout_seconds'] ?? 300;
    }

    public function handle(OutputNormalizer $normalizer, ScanOrchestrator $orchestrator): void
    {
        // CRITICAL: Disable PHP and Server output buffering to guarantee instant zero-latency streaming
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(1);

        try {
            $this->scanSession->refresh();
            
            if ($this->scanSession->status === 'pending') {
                Log::info("Scan job {$this->scanSession->id} is paused (pending). Skipping execution.");
                return;
            }

            $driverConfig = config("scanners.drivers.{$this->driverId}");
            if (!$driverConfig) {
                Log::error("Driver {$this->driverId} config not found.");
                $this->scanSession->update(['status' => 'failed', 'error_log' => "Driver config not found", 'finished_at' => now()]);
                return;
            }

            $defaultFlags = $driverConfig['default_flags'] ?? [];
            $flags = array_merge($defaultFlags, $this->userFlags);
            $targetValue = $this->target->value;
            $pattern = str_replace('{{TARGET}}', $targetValue, $driverConfig['command_pattern']);
            
            $commandArgs = array_merge(
                ['docker', 'run', '--rm'], 
                [$driverConfig['image']],
                explode(' ', $pattern),
                $flags
            );

            Log::info("Executing driver {$this->driverId}: " . implode(' ', $commandArgs));

            // Use Symfony Process directly for advanced memory management
            $process = new \Symfony\Component\Process\Process($commandArgs);
            $process->setTimeout($this->timeout);
            
            // Trick the Docker container into line-buffering mode (Instant Streaming)
            if (\Symfony\Component\Process\Process::isPtySupported()) {
                $process->setPty(true);
            }
            
            // CRITICAL: Disable output buffering to prevent memory leaks during long scans
            $process->disableOutput();

            $buffer = '';

            // Run process and capture chunked data in real-time
            $process->run(function ($type, $data) use (&$buffer, $normalizer, $orchestrator) {
                $buffer .= $data;
                
                // Process stream line by line as chunks arrive
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    $this->processLine(trim($line), $normalizer, $orchestrator);
                }
            });

            // Flush remaining buffer
            if (!empty(trim($buffer))) {
                $this->processLine(trim($buffer), $normalizer, $orchestrator);
            }

            if (!$process->isSuccessful()) {
                Log::error("Driver {$this->driverId} failed with exit code " . $process->getExitCode());
                // Stderr is intercepted and streamed live, so we don't have it in memory here.
                $this->scanSession->update(['status' => 'failed', 'error_log' => 'Process failed with exit code ' . $process->getExitCode(), 'finished_at' => now()]);
                return;
            }

            $this->scanSession->refresh();
            if ($this->scanSession->status === 'pending') return;

            $this->scanSession->update(['status' => 'completed', 'finished_at' => now()]);

        } catch (\Throwable $e) {
            Log::error("ExecuteScanDriverJob threw an exception: " . $e->getMessage());
            $this->scanSession->update(['status' => 'failed', 'error_log' => $e->getMessage(), 'finished_at' => now()]);
        }
    }

    private function processLine(string $line, OutputNormalizer $normalizer, ScanOrchestrator $orchestrator): void
    {
        if (trim($line) === '') return;

        // Smart Data Interception: Partial Line Reading
        // Start execution only from the first character of a valid JSON block
        $jsonStart = strpos($line, '{');
        $jsonProcessed = false;

        // If line start with '{' then it is a JSON structured data
        if ($jsonStart !== false) {
            $potentialJson = substr($line, $jsonStart);
            $decoded = json_decode($potentialJson, true);

            // If it parses successfully, it's valid JSON structured data
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $jsonProcessed = true;
                
                // 1. Pre-Normalization Broadcast: Broadcast any preceding UI text IMMEDIATELY
                $prefixText = substr($line, 0, $jsonStart);
                // If line not empty after stripping ANSI color codes
                if (trim($prefixText) !== '') {
                    $cleanPrefix = $this->stripAnsi($prefixText);
                    if ($cleanPrefix !== '') {
                        event(new \App\Events\TerminalLogEvent($this->scanSession->id, $cleanPrefix));
                    }
                }

                // 2. Normalization and Interception
                $findings = $normalizer->normalize($potentialJson, $this->driverId, $this->target->id, $this->scanSession->id);
                
                if (!empty($findings)) {
                    foreach ($findings as $finding) {
                        event(new \App\Events\ScanResultEvent($this->scanSession->id, $finding->toArray()));
                    }
                    $orchestrator->evaluateTriggers($this->scanSession, $this->target, $this->driverId, $findings);
                }
                
                // Return immediately to strictly prevent raw JSON strings from reaching the visual terminal
                return;
            }
        }

        // If no JSON was processed, treat the entire line as a Terminal Log
        if (!$jsonProcessed) {
            $cleanLine = $this->stripAnsi($line);
            if ($cleanLine !== '') {
                event(new \App\Events\TerminalLogEvent($this->scanSession->id, $cleanLine));
            }
        }
    }

    /**
     * Clean Output: Strip special characters, ANSI escape codes, and formatting artifacts.
     */
    private function stripAnsi(string $text): string
    {
        // Strip standard ANSI color/terminal escape codes (e.g. \u001b[0;36m)
        $text = preg_replace('/\x1B\[[0-9;]*[a-zA-Z]/', '', $text);
        
        // Strip non-printable ASCII characters (except newline \n and tab \t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return trim($text);
    }
}
