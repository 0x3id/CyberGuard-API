# Two-Factor Authentication (2FA) with Google Authenticator

This document explains how to use the 2FA implementation in CyberGuard.

## Overview

The application now supports Two-Factor Authentication (2FA) using Google Authenticator (or compatible apps like Microsoft Authenticator, Authy, etc.). When 2FA is enabled, users must provide both their password and a 6-digit code from their authenticator app to log in.

## Database Schema

The necessary database fields are already in the `users` table:
- `two_factor_secret` (string, nullable): Encrypted secret key for the authenticator
- `two_factor_enabled` (boolean, default: false): Whether 2FA is enabled for this user

## API Endpoints

### Authentication

#### 1. Login (`POST /api/auth/login`)
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Response (No 2FA):**
```json
{
    "status": "success",
    "message": "Login successful",
    "data": {
        "requires_2fa": false,
        "user": {
            "id": "uuid",
            "email": "user@example.com",
            "full_name": "John Doe",
            "job_title": "Developer"
        },
        "token": "auth-token"
    }
}
```

**Response (2FA Required):**
```json
{
    "status": "success",
    "message": "2FA verification required",
    "data": {
        "requires_2fa": true,
        "email": "user@example.com"
    }
}
```

#### 2. Setup 2FA (`POST /api/auth/2fa/setup`)
**Authentication:** Required (Bearer token)

Initiates 2FA setup and returns a QR code for scanning.

**Response:**
```json
{
    "status": "success",
    "message": "2FA setup initiated",
    "data": {
        "qr_code": "<svg>...</svg>",
        "secret": "JBSWY3DPEBLW64TMMQQQ===="
    }
}
```

The user should scan the QR code with their authenticator app.

#### 3. Enable 2FA (`POST /api/auth/2fa/enable`)
**Authentication:** Required (Bearer token)

Verifies the 2FA code and enables 2FA for the user.

**Request:**
```json
{
    "code": "123456"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "2FA enabled successfully"
}
```

#### 4. Verify 2FA (`POST /api/auth/2fa/verify`)
**Authentication:** Not required

Verifies the 2FA code after initially logging in (when `requires_2fa` is true).

**Request:**
```json
{
    "email": "user@example.com",
    "code": "123456"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "2FA verified successfully",
    "data": {
        "user": {
            "id": "uuid",
            "email": "user@example.com",
            "full_name": "John Doe",
            "job_title": "Developer"
        },
        "token": "auth-token"
    }
}
```

#### 5. Disable 2FA (`POST /api/auth/2fa/disable`)
**Authentication:** Required (Bearer token)

Disables 2FA for the user (requires verification).

**Request:**
```json
{
    "code": "123456"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "2FA disabled successfully"
}
```

#### 6. Get 2FA Status (`GET /api/auth/2fa/status`)
**Authentication:** Required (Bearer token)

Returns whether 2FA is enabled for the user.

**Response:**
```json
{
    "status": "success",
    "data": {
        "two_factor_enabled": true
    }
}
```

## Implementation Flow

### User Registration
1. User registers normally
2. Email verification is required
3. User can optionally enable 2FA after registration

### Enabling 2FA
1. User calls `/api/auth/2fa/setup` to get QR code
2. User scans QR code with authenticator app (Google Authenticator, Microsoft Authenticator, Authy)
3. User enters the 6-digit code into the app
4. User calls `/api/auth/2fa/enable` with the code to verify and enable 2FA
5. 2FA is now active for the user

### Login with 2FA
1. User sends email and password via `/api/auth/login`
2. If 2FA is enabled, response includes `requires_2fa: true` and `email`
3. User gets code from their authenticator app
4. User calls `/api/auth/2fa/verify` with email and 6-digit code
5. If code is valid, user receives authentication token
6. User can now access protected resources

### Disabling 2FA
1. User calls `/api/auth/2fa/disable` with current 2FA code
2. If code is valid, 2FA is disabled
3. Next login only requires email and password

## Security Features

1. **Encrypted Secrets**: 2FA secrets are encrypted in the database using Laravel's encryption
2. **Code Verification**: 6-digit codes are verified in real-time using the Google2FA library
3. **Time-based OTP**: Uses TOTP (Time-based One-Time Password) standard for compatibility
4. **Session Protection**: QR codes are temporarily stored in session during setup

## Compatible Authenticator Apps

- Google Authenticator (iOS, Android)
- Microsoft Authenticator (iOS, Android, Windows)
- Authy (iOS, Android, Windows, Mac)
- FreeOTP (iOS, Android)
- Duo Security (iOS, Android)
- Any TOTP-compatible app

## Testing

### Manual Testing with cURL

```bash
# 1. Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "full_name": "Test User",
    "job_title": "Developer"
  }'

# 2. Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'

# 3. Setup 2FA
curl -X POST http://localhost:8000/api/auth/2fa/setup \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"

# 4. Enable 2FA (with code from authenticator app)
curl -X POST http://localhost:8000/api/auth/2fa/enable \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "123456"
  }'

# 5. Login with 2FA
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'

# 6. Verify 2FA Code
curl -X POST http://localhost:8000/api/auth/2fa/verify \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "code": "123456"
  }'
```

## Error Handling

The API returns appropriate HTTP status codes:
- `200`: Success
- `400`: Bad request (invalid data, setup not initiated, etc.)
- `401`: Unauthorized (invalid credentials, invalid 2FA code, etc.)

## File Structure

- `app/Http/Controllers/TwoFactorAuthController.php` - 2FA API endpoints
- `app/Services/TwoFactorService.php` - 2FA business logic service
- `app/Models/User.php` - User model with 2FA attributes
- `routes/api.php` - 2FA routes
- `database/migrations/0001_01_01_000000_create_users_table.php` - User table with 2FA columns

## Frontend Integration

When building a frontend, follow this flow:

1. **Login Page**: Submit email/password
2. Check response:
   - If `requires_2fa: true`, show 2FA verification page
   - Otherwise, save token and redirect to dashboard
3. **2FA Verification Page**: 
   - Allow user to enter 6-digit code
   - Submit to `/api/auth/2fa/verify`
   - Save token and redirect on success

## Recovery Options (Future Enhancements)

Consider implementing:
- Recovery codes for account recovery (if user loses their device)
- Backup authentication methods
- Used code history to prevent code reuse
- Device management (remember device for 30 days)

## Troubleshooting

### Issue: QR code not scanning
- Ensure the secret is properly generated
- Check that app name and email are displayed correctly
- Try manually entering the secret if QR code fails

### Issue: Invalid code error
- Ensure the authenticator app is synchronized with the server time
- Check that the user is entering the latest 6-digit code
- Codes expire after 30 seconds

### Issue: 2FA codes not working after database migration
- Ensure the `two_factor_secret` is properly encrypted/decrypted
- Check the APP_ENCRYPTION_KEY in .env file
- Verify the secret hasn't been corrupted

## References

- [Google2FA Laravel](https://github.com/antonioribeiro/google2fa-laravel)
- [TOTP Standard](https://tools.ietf.org/html/rfc6238)
- [Laravel Encryption](https://laravel.com/docs/encryption)
