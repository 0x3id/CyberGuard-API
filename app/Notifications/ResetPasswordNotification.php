<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
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
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/')), '/');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        $userName = $notifiable->full_name
            ?? (Str::before((string) $notifiable->email, '@') ?: 'there');

        return (new MailMessage)
            ->subject('Reset Password – CyberGuard')
            ->greeting('Reset Your Password')
            ->with([
                'userName' => $userName,
                'expiryText' => 'This password reset link will expire in <strong style="color:#94a3b8;font-weight:600;font-style:normal;">60 minutes</strong>.',
            ])
            ->line('We received a password reset request for your <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> account.')
            ->line('Click the button below to choose a new password and restore access to your security profile.')
            ->action('Reset Password', $resetUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
