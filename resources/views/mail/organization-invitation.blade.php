@extends('mail.layouts.cyberguard', [
    'pageTitle' => 'Organization Invitation – CyberGuard',
    'emailGreeting' => 'You\'re Invited to Join',
    'userName' => $userName,
    'headerIcon' => 'users',
    'actionUrl' => $inviteUrl,
    'actionText' => 'Accept Invitation',
    'expiryText' => 'This secure invitation link will expire in <strong style="color:#cbd5e1;font-style:normal;font-weight:600;">' . ($expiryHours ?? 24) . ' hours</strong>.',
    'fallbackUrl' => $inviteUrl,
    'footerNote' => 'If you did not expect this invitation, you can safely ignore this email.',
])

@section('message')
    <p style="margin:0 0 12px;">
        You have been invited to join the
        <strong style="color:#3b82f6;font-weight:700;">{{ $organizationName }}</strong>
        workspace on <strong style="color:#3b82f6;font-weight:700;">CyberGuard</strong>
        as a <strong style="color:#3b82f6;font-weight:700;">{{ ucfirst($role) }}</strong>.
    </p>
    <p style="margin:0;">
        Click the button below to accept your invitation and access your organization's security workspace.
    </p>
@endsection
