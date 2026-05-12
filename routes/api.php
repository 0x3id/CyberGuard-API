<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\FindingController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProjectInvitationController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\SubscriptionBillingController;
use App\Http\Controllers\PaymobWebhookController;
use App\Models\ProjectInvitation;


Route::prefix('auth')->group(function() {
    // ── Authentication Route ─────────────────
    // 1. Register Route
    Route::post('/register', [AuthenticationController::class, 'register']);
    // 2. Login Route
    Route::post('/login', [AuthenticationController::class, 'login']);
    // 3. Logout Route
    Route::post('/logout', [AuthenticationController::class, 'logout'])->middleware('auth:sanctum');

    // 4. Forgot Password
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
    // 5. Reset Password
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
    // 6. password.reset route needed by Laravel reset email
    Route::get('/password/reset/{token}', function ($token) {
        return response()->json([
            'status' => 'success',
            'message' => 'Use this token to reset your password',
            'token' => $token
        ]);
    })->name('password.reset');

    // 7. Get The User Data
    Route::get('/me', function (Request $request) {
        return response()->json(['user' => $request->user()]);
    })->middleware('auth:sanctum');

    // 8. Authentication health check
    Route::get('/status', function (Request $request) {
        return response()->json(['status' => 'success', 'user' => $request->user()]);
    })->middleware('auth:sanctum');


    // ── 2FA Routes ─────────────────
    // 1. Setup 2FA (returns QR code)
    Route::post('/2fa/setup', [TwoFactorAuthController::class, 'setup'])->middleware('auth:sanctum');
    // 2. Enable 2FA
    Route::post('/2fa/enable', [TwoFactorAuthController::class, 'enable'])->middleware('auth:sanctum');
    // 3. Disable 2FA
    Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable'])->middleware('auth:sanctum');
    // 4. Verify 2FA code during login
    Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify']);
    // 5. Get 2FA status
    Route::get('/2fa/status', [TwoFactorAuthController::class, 'status'])->middleware('auth:sanctum');
});

Route::prefix('email')->group(function() {
    // ── Email Verify Routes ─────────────────
    // 1. Send Email Verification
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'sendEmailVerification'])
        ->middleware('signed')->name('verification.verify');
    // 2. Resend Email Verification
    Route::post('/verification-notification/resend', [EmailVerificationController::class, 'resendEmailVerification'])->middleware(['throttle:6,1'])->name('verification.send');
});

// ── Billing Routes ─────────────────
// 1. Paymob Webhook
Route::post('billing/paymob/webhook', PaymobWebhookController::class);
// 2. Paymob Redirect after payment
Route::get('billing/paymob/redirect', [UserSubscriptionController::class, 'handleRedirect']);
// 3. Get Plans
Route::get('/billing/plans', [SubscriptionBillingController::class, 'plans']);

Route::middleware('auth:sanctum')->group(function() {
    // ── User subscription & Egypt (Paymob) billing ─────────────────
    // 1. Get Subscription Details
    Route::get('subscription', [UserSubscriptionController::class, 'show']);
    // 2. Update Subscription
    Route::patch('subscription', [UserSubscriptionController::class, 'update']);
    // 3. Checkout Subscription
    Route::post('billing/checkout', [SubscriptionBillingController::class, 'checkout']);
    // 4. Get Billing Orders
    Route::get('billing/orders', [SubscriptionBillingController::class, 'orders']);

    // ── Projects Resource ─────────────────
    Route::apiResource('projects', ProjectController::class);

    // ── Invitations ─────────────────────
    // Create Link To Invite User To Project
    Route::post('projects/{project}/invite' , [ProjectInvitationController::class, 'invite']);
    // Accept Invitation
    Route::post('invitations/{token}/accept' , [ProjectInvitationController::class, 'accept']);
    // Show Invitation Details
    Route::get('invitations/{token}', [ProjectInvitationController::class, 'showInvitation']);
    // Cancel invitation
    Route::delete('invitations/{token}/reject' , [ProjectInvitationController::class, 'reject']);

    // ── Collaborators ─────────────────────
    // Remove User From Project
    Route::delete('projects/{project}/collaborators/{user}', [CollaboratorController::class, 'remove']);
    // Change User Role
    Route::patch('projects/{project}/collaborators/{user}', [CollaboratorController::class, 'changeRole']);
    // List of project collaborators
    Route::get('/projects/{project}/collaborators', [CollaboratorController::class, 'getAll']);

    // ── Targets ─────────────────────
    // Add new Target
    Route::post('projects/{project}/targets', [TargetController::class, 'addNewTarget']);
    // Get Target Details
    Route::get('targets/{target}', [TargetController::class, 'getTargetDetails']);
    // Delete Target
    Route::delete('projects/{project}/targets/{target}', [TargetController::class, 'deleteTarget']);
    // Get All Targets Of Project
    Route::get('projects/{project}/targets', [TargetController::class, 'getAllTargets']);
    // Update Target
    Route::patch('projects/{project}/targets/{target}', [TargetController::class, 'updateTarget']);

    // ── Scans ─────────────────────
    // Get Available Scanners
    Route::get('/scanners', [ScanController::class, 'getAvailableScanners']);
    // Start Scan
    Route::post('/scan/start', [ScanController::class, 'startScan']);
    // Get Scan Status
    Route::get('/scan/{scanJobId}/status', [ScanController::class, 'getScanStatus']);
    // Get Scan Findings
    Route::get('/scan/{scanJobId}/findings', [ScanController::class, 'fetchFindings']);

    // ── Findings ───────────────────
    // Get All Findings of target
    Route::get('targets/{target}/findings', [FindingController::class, 'index']);
    // Update Finding Status
    Route::patch('findings/{finding}/status', [FindingController::class, 'updateStatus']);
    Route::get('targets/{target}/endpoints', [FindingController::class, 'getEndpoints']);

});
