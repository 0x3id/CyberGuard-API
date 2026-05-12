<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScanResultEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scanJobId;
    public $finding;

    public function __construct(string $scanJobId, array $finding)
    {
        $this->scanJobId = $scanJobId;
        $this->finding = $finding;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('scan.' . $this->scanJobId);
    }

    public function broadcastAs(): string
    {
        return 'scan-results';
    }
}
