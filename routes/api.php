<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordResetController;


/**
 * Authentication Route
 */
Route::prefix('auth')->group(function() {
    // - Register Route
    Route::post('/register', [AuthenticationController::class, 'register']);
    // - Login Route
    Route::post('/login', [AuthenticationController::class, 'login']);
    // - Logout Route
    Route::post('/logout', [AuthenticationController::class, 'logout'])->middleware('auth:sanctum');

    // - Forgot Password
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
    // - Reset Password
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
    // - password.reset route needed by Laravel reset email
    Route::get('/password/reset/{token}', function ($token) {
        return response()->json([
            'status' => 'success',
            'message' => 'Use this token to reset your password',
            'token' => $token
        ]);
    })->name('password.reset');

    // - Get The User Data
    Route::get('/me', function (Request $request) {
        return response()->json(['user' => $request->user()]);
    })->middleware('auth:sanctum');

    // - Authentication health check
    Route::get('/status', function (Request $request) {
        return response()->json(['status' => 'success', 'user' => $request->user()]);
    })->middleware('auth:sanctum');

    /**
     * 2FA Routes
     */
    // - Setup 2FA (returns QR code)
    Route::post('/2fa/setup', [TwoFactorAuthController::class, 'setup'])->middleware('auth:sanctum');
    // - Enable 2FA
    Route::post('/2fa/enable', [TwoFactorAuthController::class, 'enable'])->middleware('auth:sanctum');
    // - Disable 2FA
    Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable'])->middleware('auth:sanctum');
    // - Verify 2FA code during login
    Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify']);
    // - Get 2FA status
    Route::get('/2fa/status', [TwoFactorAuthController::class, 'status'])->middleware('auth:sanctum');
});

/**
 * Email Verify Routes 
 */
// - Send Email Verification
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'sendEmailVerification'])
        ->middleware('signed')->name('verification.verify');
// - Resend Email Verification
Route::post('/email/verification-notification/{id}', [EmailVerificationController::class, 'resendEmailVerification'])
        ->middleware(['throttle:6,1','auth:sanctum'])->name('verification.send');




Route::get('/profile/{id}' , function($id) {
    $user = User::findOrFail($id);
    if($user->hasVerifiedEmail())
    {
        return response()->json([
        'msg' => "Hello in profile"
    ]);
    }
    return response()->json([
        'msg' => "Please Verifiy Your Email"
    ], 401);
})->middleware(['auth:sanctum']);
