<?php

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorService
{
    protected $google2fa;
    protected $qrWriter;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->qrWriter = new PngWriter();
    }

    /**
     * Generate a new secret key
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Get QR code URL for setup
     */
    public function getQRCodeUrl(string $companyName, string $email, string $secret): string
    {
        $qrUrl = 'otpauth://totp/' . urlencode($companyName . ':' . $email) . '?secret=' . $secret . '&issuer=' . urlencode($companyName);
        
        $qrCode = new QrCode($qrUrl);
        $result = $this->qrWriter->write($qrCode);
        return $result->getDataUri();
    }

    /**
     * Verify a 2FA code
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * Enable 2FA for user
     */
    public function enableForUser(User $user, string $secret): void
    {
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_enabled = true;
        $user->save();
    }

    /**
     * Disable 2FA for user
     */
    public function disableForUser(User $user): void
    {
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->save();
    }

    /**
     * Get decrypted secret for user
     */
    public function getDecryptedSecret(User $user): ?string
    {
        if (!$user->two_factor_secret) {
            return null;
        }
        
        return decrypt($user->two_factor_secret);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isEnabled(User $user): bool
    {
        return (bool) $user->two_factor_enabled;
    }

    /**
     * Create recovery codes (optional - for additional security)
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = dechex(random_bytes(4));
        }
        return $codes;
    }

    /**
     * Verify recovery code format
     */
    public function verifyRecoveryCode(string $code): bool
    {
        return preg_match('/^[a-f0-9]{8}$/', strtolower($code)) === 1;
    }
}
