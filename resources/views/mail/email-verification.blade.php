@extends('mail.layouts.cyberguard', [
    'pageTitle' => 'Verify Your Email – CyberGuard',
    'emailGreeting' => 'Confirm Your Identity',
    'userName' => $userName,
    'headerIcon' => 'envelope',
    'actionUrl' => $verificationUrl,
    'actionText' => 'Verify Your Email',
    'expiryText' => 'This secure link will expire in <strong style="color:#cbd5e1;font-style:normal;font-weight:600;">' . ($expiryMinutes ?? 60) . ' minutes</strong>.',
    'fallbackUrl' => $verificationUrl,
    'footerNote' => 'If you didn\'t create a CyberGuard account, you can safely ignore this email.',
])

@section('message')
    <p style="margin:0 0 12px;">
        A request has been made to associate this email address with a
        <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> security profile.
    </p>
    <p style="margin:0;">
        To ensure the integrity of our network, please verify your access by clicking the button below.
    </p>
@endsection
