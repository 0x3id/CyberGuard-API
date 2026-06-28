@extends('mail.layouts.cyberguard', [
    'pageTitle' => 'Reset Password – CyberGuard',
    'emailGreeting' => 'Reset Your Password',
    'userName' => $userName,
    'headerIcon' => 'key',
    'actionUrl' => $resetUrl,
    'actionText' => 'Reset Password',
    'expiryText' => 'This password reset link will expire in <strong style="color:#cbd5e1;font-style:normal;font-weight:600;">' . ($expiryMinutes ?? 60) . ' minutes</strong>.',
    'fallbackUrl' => $resetUrl,
    'footerNote' => 'If you did not request a password reset, no further action is required.',
])

@section('message')
    <p style="margin:0 0 12px;">
        We received a password reset request for your
        <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong> account.
    </p>
    <p style="margin:0;">
        Click the button below to choose a new password and restore access to your security profile.
    </p>
@endsection
