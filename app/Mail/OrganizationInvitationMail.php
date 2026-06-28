<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public OrganizationInvitation $invitation;

    public function __construct(OrganizationInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join ' . $this->invitation->organization->name . ' on CyberGuard',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/')), '/');
        $inviteUrl = $frontendUrl . '/organizations/join?token=' . $this->invitation->token;
        $userName = Str::before($this->invitation->email, '@');

        return new Content(
            view: 'mail.organization-invitation',
            with: [
                'userName' => $userName !== '' ? $userName : 'there',
                'organizationName' => $this->invitation->organization->name,
                'role' => $this->invitation->role,
                'inviteUrl' => $inviteUrl,
                'expiryHours' => 24,
            ],
        );
    }
}
