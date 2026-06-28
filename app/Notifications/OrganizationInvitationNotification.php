<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation->loadMissing('organization');
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/')), '/');
        $inviteUrl = $frontendUrl . '/organizations/join?token=' . $invitation->token;
        $userName = Str::before($invitation->email, '@') ?: 'there';
        $expiryHours = 24;

        return (new MailMessage)
            ->subject('You have been invited to join ' . $invitation->organization->name . ' on CyberGuard')
            ->greeting("You're Invited to Join")
            ->with([
                'userName' => $userName,
                'expiryText' => 'This secure invitation link will expire in <strong style="color:#94a3b8;font-weight:600;font-style:normal;">' . $expiryHours . ' hours</strong>.',
            ])
            ->line('You have been invited to join the <strong style="color:#3b82f6;font-weight:700;">' . $invitation->organization->name . '</strong> workspace on <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> as a <strong style="color:#3b82f6;font-weight:700;">' . ucfirst($invitation->role) . '</strong>.')
            ->line('Click the button below to accept your invitation and access your organization\'s security workspace.')
            ->action('Accept Invitation', $inviteUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'email' => $this->invitation->email,
            'role' => $this->invitation->role,
        ];
    }
}
