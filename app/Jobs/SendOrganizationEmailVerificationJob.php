<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Notifications\OrganizationEmailVerificationNotification;

class SendOrganizationEmailVerificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public User $user;
    public string $orderId;
    public string $corporateEmail;

    public function __construct(User $user, string $orderId, string $corporateEmail)
    {
        $this->user = $user;
        $this->orderId = $orderId;
        $this->corporateEmail = $corporateEmail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Build the verification URL for the frontend
        $frontendUrl = env('FRONTEND_URL', 'https://cyberguard-pro-eta.vercel.app/');
        $verificationUrl = $this->generateVerificationUrl($this->user, $frontendUrl);

        \Illuminate\Support\Facades\Notification::route('mail', $this->corporateEmail)
            ->notify(new OrganizationEmailVerificationNotification($verificationUrl));
    }

    // private function generateVerificationUrl($user, $frontendUrl)
    // {
    //     $signedUrl = url()->temporarySignedRoute(
    //         'organizations.corporate-email.verify',
    //         now()->addMinutes(60),
    //         [
    //             'billing_order' => $this->orderId,
    //             'email' => $this->corporateEmail,
    //         ]
    //     );
    //     // Pass the signed URL as a query param to the frontend
    //     return rtrim($frontendUrl, '/') . '/verify-email.html?verify_url=' . urlencode($signedUrl);
    // }
    private function generateVerificationUrl($user,string $frontendUrl)
    {
        $expiration = now()->addMinutes(60);

        $params = [
            'billing_order' => $this->orderId,
            'email' => strtolower($this->corporateEmail),
            'expires' => $expiration->getTimestamp(),
        ];

        $urlForSignature = route('organizations.corporate-email.verify', $params);
        $signature = hash_hmac('sha256', $urlForSignature, config('app.key'));

        $params['signature'] = $signature;

        // بناء لينك الـ Frontend النهائي المباشر
        return rtrim($frontendUrl, '/') . '/verify-email.html?' . http_build_query($params);
    }
}
