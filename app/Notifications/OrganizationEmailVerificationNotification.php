<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrganizationEmailVerificationNotification extends Notification implements ShouldQueue
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
        $email = $notifiable->routes['mail']
            ?? (method_exists($notifiable, 'getEmailForVerification') ? $notifiable->getEmailForVerification() : null)
            ?? ($notifiable->email ?? null);

        if (is_array($email)) {
            $email = $email[0] ?? null;
        }

        $userName = is_string($email) && $email !== ''
            ? Str::before($email, '@')
            : 'there';

        return (new MailMessage)
            ->subject('Verify Your Organization Email Address – CyberGuard')
            ->greeting('Confirm Your Corporate Email')
            ->with([
                'userName' => $userName,
                'expiryText' => 'This secure link will expire in <strong style="color:#94a3b8;font-weight:600;font-style:normal;">60 minutes</strong>.',
            ])
            ->line('Your organization workspace on <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> requires corporate email verification before payment and activation.')
            ->line('Please verify your corporate email address by clicking the button below to continue onboarding.')
            ->action('Verify Corporate Email', $this->verificationUrl);
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
