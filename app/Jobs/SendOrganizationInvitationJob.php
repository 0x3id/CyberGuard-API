<?php

namespace App\Jobs;

use App\Models\OrganizationInvitation;
use App\Mail\OrganizationInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOrganizationInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public OrganizationInvitation $invitation;

    /**
     * Create a new job instance.
     */
    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->invitation->email)->send(new OrganizationInvitationMail($this->invitation));
        } catch (\Exception $e) {
            Log::error("Failed to send organization invitation to {$this->invitation->email}: " . $e->getMessage());
            // Since this is a critical flow, we might want to throw the exception to let the queue retry
            throw $e;
        }
    }
}
