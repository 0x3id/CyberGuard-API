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
use App\Http\Controllers\DashboardController;
use App\Models\ProjectInvitation;

// ── Authentication Route ─────────────────
Route::prefix('auth')->group(function() {
    Route::controller(AuthenticationController::class)->group(function() {
        // 1. Register Route
        Route::post('/register', 'register');
        // 2. Login Route
        Route::post('/login', 'login');
        // 3. Logout Route
        Route::post('/logout', 'logout')->middleware('auth:sanctum');
    });

    Route::controller(PasswordResetController::class)->group(function() {
        // 4. Forgot Password
        Route::post('/forgot-password', 'forgot');
        // 5. Reset Password
        Route::post('/reset-password', 'reset');
    });
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
    Route::controller(TwoFactorAuthController::class)->group(function() {
        // 1. Setup 2FA (returns QR code)
        Route::post('/2fa/setup', 'setup')->middleware('auth:sanctum');
        // 2. Enable 2FA
        Route::post('/2fa/enable', 'enable')->middleware('auth:sanctum');
        // 3. Disable 2FA
        Route::post('/2fa/disable', 'disable')->middleware('auth:sanctum');
        // 4. Verify 2FA code during login
        Route::post('/2fa/verify', 'verify');
        // 5. Get 2FA status
        Route::get('/2fa/status', 'status')->middleware('auth:sanctum');
    });

});

// ── Email Verify Routes ─────────────────
Route::prefix('email')->group(function() {
    // 1. Send Email Verification
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'sendEmailVerification'])
        ->middleware('signed')->name('verification.verify');
    // 2. Resend Email Verification
    Route::post('/verification-notification/resend', [EmailVerificationController::class, 'resendEmailVerification'])
        ->middleware(['throttle:6,1'])->name('verification.send');
});

// ── Billing Routes ─────────────────
// 1. Paymob Webhook
Route::post('billing/paymob/webhook', PaymobWebhookController::class);
// 2. Paymob Redirect after payment
Route::get('billing/paymob/redirect', [UserSubscriptionController::class, 'handleRedirect']);
// 3. Get Plans
Route::get('/billing/plans', [SubscriptionBillingController::class, 'plans']);

Route::middleware('auth:sanctum')->group(function() {
    // ── Dashboard ──────────────────────────────────────────────────
    // Aggregate security metrics for the Risk Management Dashboard
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics']);

    // ── Projects Resource ─────────────────
    Route::apiResource('projects', ProjectController::class);

    // ── User subscription & Egypt (Paymob) billing ─────────────────
    Route::controller(UserSubscriptionController::class)->group(function() {
        // 1. Get Subscription Details
        Route::get('subscription', 'show');
        // 2. Update Subscription
        Route::patch('subscription', 'update');
    });
    Route::controller(SubscriptionBillingController::class)->group(function() {
        // 3. Checkout Subscription
        Route::post('billing/checkout', 'checkout');
        // 4. Get Billing Orders
        Route::get('billing/orders', 'orders');
    });

    // ── Invitations ─────────────────────
    Route::controller(ProjectInvitationController::class)->group(function() {
        // 1. Create Link To Invite User To Project
        Route::post('projects/{project}/invite', 'invite');
        // 2. Accept Invitation
        Route::post('invitations/{token}/accept', 'accept');
        // 3. Show Invitation Details
        Route::get('invitations/{token}', 'showInvitation');
        // 4. Cancel invitation
        Route::delete('invitations/{token}/reject', 'reject');
        // 5. Get All Invitations Of Project
        Route::get('projects/{project}/invitations/pending', 'pendingInvitation');
    });

    // ── Collaborators ─────────────────────
    Route::controller(CollaboratorController::class)->group(function() {
        // Remove User From Project
        Route::delete('projects/{project}/collaborators/{user}', 'remove');
        // Change User Role
        Route::patch('projects/{project}/collaborators/{user}', 'changeRole');
        // List of project collaborators
        Route::get('/projects/{project}/collaborators', 'getAll');
    });

    // ── Targets ─────────────────────
    Route::controller(TargetController::class)->group(function() {
        // 1. Add new Target
        Route::post('projects/{project}/targets', 'addNewTarget');
        // 2. Get Target Details
        Route::get('targets/{target}', 'getTargetDetails');
        // 3. Delete Target
        Route::delete('projects/{project}/targets/{target}', 'deleteTarget');
        // 4. Get All Targets Of Project
        Route::get('projects/{project}/targets', 'getAllTargets');
        // 5. Get All Target That user have access to scan on it
        Route::get('/targets', 'allTargets');
        // 6. Update Target
        Route::patch('projects/{project}/targets/{target}', 'updateTarget');
    });

    // ── Scans ─────────────────────
    Route::controller(ScanController::class)->group(function() {
        // 1. Get Available Scanners
        Route::get('/scanners', [ScanController::class, 'getAvailableScanners']);
        // 2. Start Scan
        Route::post('/scan/start', [ScanController::class, 'startScan']);
        // 3. Get Scan Status
        Route::get('/scan/{scanJobId}/status', [ScanController::class, 'getScanStatus']);
        // 4. Get Scan Findings
        Route::get('/scan/{scanJobId}/findings', [ScanController::class, 'fetchFindings']);
        // 5. Get All ScanJobs Of Project
        Route::get('/projects/{project}/scans', [ScanController::class, 'projectScans']);
        // 6. Get All ScansJobs Of Target
        Route::get('/targets/{target}/scans', [ScanController::class, 'targetScans']);
        // 7. Pause Scan
        Route::post('/scan/{scanJobId}/pause', [ScanController::class, 'pauseScan']);
        // 8. Continue Paused Scans
        Route::post('/scan/{scanJobId}/continue', [ScanController::class, 'continueScan']);
        // 9. Cancel Scan
        Route::post('/scan/{scanJobId}/cancel', [ScanController::class, 'cancelScan']);
    });

    // ── Findings ───────────────────
    Route::controller(FindingController::class)->group(function() {
        // 1. Get All Findings of target
        Route::get('/targets/{target}/findings', 'index');
        // 2. Get All Findings of project
        Route::get('/projects/{project}/findings', 'getProjectFindings');
        // 3. Update Finding Status
        Route::patch('/findings/{finding}/status', 'updateStatus');
        // 4. Update Finding Severity
        Route::patch('/findings/{finding}/severity', 'updateSeverity');
        // 5. Upload New Findings
        Route::post('/targets/{target}/findings', 'uploadFinding');
        Route::get('targets/{target}/endpoints', 'getEndpoints');
    });

});
