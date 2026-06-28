<?php

namespace App\Jobs;

use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendOrganizationInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public OrganizationInvitation $invitation;

    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function handle(): void
    {
        try {
            Notification::route('mail', $this->invitation->email)
                ->notify(new OrganizationInvitationNotification($this->invitation));
        } catch (\Exception $e) {
            Log::error("Failed to send organization invitation to {$this->invitation->email}: " . $e->getMessage());
            throw $e;
        }
    }
}
