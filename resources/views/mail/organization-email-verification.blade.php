@extends('mail.layouts.cyberguard', [
    'pageTitle' => 'Verify Organization Email – CyberGuard',
    'emailGreeting' => 'Confirm Your Corporate Email',
    'userName' => $userName,
    'headerIcon' => 'envelope',
    'actionUrl' => $verificationUrl,
    'actionText' => 'Verify Corporate Email',
    'expiryText' => 'This secure link will expire in <strong style="color:#cbd5e1;font-style:normal;font-weight:600;">' . ($expiryMinutes ?? 60) . ' minutes</strong>.',
    'fallbackUrl' => $verificationUrl,
    'footerNote' => 'If you did not initiate organization onboarding on CyberGuard, you can safely ignore this email.',
])

@section('message')
    <p style="margin:0 0 12px;">
        Your organization workspace on
        <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong>
        requires corporate email verification before payment and activation.
    </p>
    <p style="margin:0;">
        Please verify your corporate email address by clicking the button below to continue onboarding.
    </p>
@endsection
