<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $verificationUrl,
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
        $userName = $notifiable->full_name
            ?? (Str::before((string) $notifiable->email, '@') ?: 'there');

        return (new MailMessage)
            ->subject('Verify Your Email Address – CyberGuard')
            ->greeting('Confirm Your Identity')
            ->with([
                'userName' => $userName,
                'expiryText' => 'This secure link will expire in <strong style="color:#94a3b8;font-weight:600;font-style:normal;">60 minutes</strong>.',
            ])
            ->line('A request has been made to associate this email address with a <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> security profile.')
            ->line('To ensure the integrity of our network, please verify your access by clicking the button below.')
            ->action('Verify Your Email', $this->verificationUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'verificationUrl' => $this->verificationUrl,
        ];
    }
}
