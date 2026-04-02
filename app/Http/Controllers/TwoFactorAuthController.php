<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TwoFactorAuthController extends Controller
{
    protected $google2fa;
    protected $qrWriter;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->qrWriter = new PngWriter();
    }

    /**
     * Generate 2FA secret and return QR code
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Generate new secret
        $secret = $this->google2fa->generateSecretKey();
        
        // Generate QR code URL for Google Authenticator
        $qrUrl = 'otpauth://totp/' . urlencode(env('APP_NAME') . ':' . $user->email) . '?secret=' . $secret . '&issuer=' . urlencode(env('APP_NAME'));
        
        $qrCode = new QrCode($qrUrl);
        $result = $this->qrWriter->write($qrCode);
        $qrCodeUrl = $result->getDataUri();

        // Store temporarily in DB to support stateless API clients and avoid session mismatch
        $user->two_factor_temp_secret = encrypt($secret);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => '2FA setup initiated',
            'data' => [
                'qr_code' => $qrCodeUrl,
                'secret' => $secret
            ]
        ]);
    }

    /**
     * Verify the code and enable 2FA
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|numeric|digits:6'
        ]);

        $user = $request->user();

        $secret = session('2fa_secret') ?? ($user->two_factor_temp_secret ? decrypt($user->two_factor_temp_secret) : null);

        if (!$secret) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA setup not initiated. Please start setup first.'
            ], 400);
        }

        // Verify the code
        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification code'
            ], 401);
        }

        // Save secret to user and clear temporary state
        $user->two_factor_secret = encrypt($secret);
        $user->two_factor_enabled = true;
        $user->two_factor_temp_secret = null;
        $user->save();

        // Clear session fallback
        session()->forget('2fa_secret');

        return response()->json([
            'status' => 'success',
            'message' => '2FA enabled successfully'
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|numeric|digits:6'
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA is not enabled'
            ], 400);
        }

        // Verify the code before disabling
        $secret = decrypt($user->two_factor_secret);
        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification code'
            ], 401);
        }

        // Disable 2FA
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => '2FA disabled successfully'
        ]);
    }

    /**
     * Verify 2FA code during login
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|numeric|digits:6',
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->two_factor_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request'
            ], 400);
        }

        // Verify the code
        $secret = decrypt($user->two_factor_secret);
        if (!$this->google2fa->verifyKey($secret, $request->code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification code'
            ], 401);
        }

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Update last login
        $user->last_login_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => '2FA verified successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'job_title' => $user->job_title,
                ],
                'token' => $token
            ]
        ]);
    }

    /**
     * Get 2FA status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ]
        ]);
    }
}
