@extends('mail.layouts.cyberguard', [
    'pageTitle' => 'Verify Your Email – CyberGuard',
    'emailGreeting' => 'Confirm Your Identity',
    'userName' => $userName ?? 'there',
    'headerIcon' => 'envelope',
    'actionUrl' => $verificationUrl,
    'actionText' => 'Verify Email Address',
    'expiryText' => 'This secure link will expire in <strong>' . ($expiryMinutes ?? 60) . ' minutes</strong>.',
    'fallbackUrl' => $verificationUrl,
    'footerNote' => 'If you did not create an account, no further action is required.',
])

@section('message')
    <p style="margin:0 0 12px;">
        Please click the button below to verify your email address on
        <strong>CyberGuard</strong>.
    </p>
    <p style="margin:0;">
        To ensure the integrity of your account, please verify your access by clicking the button above.
    </p>
@endsection
