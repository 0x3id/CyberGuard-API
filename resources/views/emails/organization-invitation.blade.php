<!DOCTYPE html>
<html>
<head>
    <title>Organization Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #333333;">You've been invited!</h2>
        <p style="color: #555555; line-height: 1.6;">
            You have been invited to join the <strong>{{ $organizationName }}</strong> workspace on CyberGuard as a <strong>{{ ucfirst($role) }}</strong>.
        </p>
        <p style="color: #555555; line-height: 1.6;">
            Click the button below to accept your invitation and join the organization. This link will expire in 24 hours.
        </p>
        <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
            <a href="{{ $inviteUrl }}" style="background-color: #0056b3; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: bold; display: inline-block;">
                Accept Invitation
            </a>
        </div>
        <p style="color: #888888; font-size: 12px; border-top: 1px solid #eeeeee; padding-top: 20px;">
            If you did not expect this invitation, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
