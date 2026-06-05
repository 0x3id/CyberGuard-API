<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerminalLogEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scanJobId;
    public $logLine;

    public function __construct(string $scanJobId, string $logLine)
    {
        $this->scanJobId = $scanJobId;
        $this->logLine = $logLine;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('scan.' . $this->scanJobId);
    }
    
    public function broadcastAs(): string
    {
        return 'terminal-log';
    }
    public function broadcastConnections(): array
    {
       return ['reverb'];
    }
}
