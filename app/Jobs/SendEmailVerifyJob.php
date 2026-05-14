<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEmailVerifyJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Build the verification URL for the frontend
        $frontendUrl = env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/');
        $verificationUrl = $this->generateVerificationUrl($this->user, $frontendUrl);

        $this->user->notify(new \App\Notifications\EmailVerificationNotification($verificationUrl));
    }

    private function generateVerificationUrl($user, $frontendUrl)
    {
        $signedUrl = url()->temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );
        // Pass the signed URL as a query param to the frontend
        return rtrim($frontendUrl, '/') . '/verify-email.html?verify_url=' . urlencode($signedUrl);
    }
}
