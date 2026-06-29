<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Core Authentication Controllers ─────────────────────────────────────────
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PasswordResetController;

// ── Personal & Core Platform Controllers ────────────────────────────────────
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\FindingController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\ProjectInvitationController;
use App\Http\Controllers\UserApiKeyController;

// ── B2B Multi-Tenancy & Billing Controllers ─────────────────────────────────
use App\Http\Controllers\UserSubscriptionController;
use App\Http\Controllers\SubscriptionBillingController;
use App\Http\Controllers\PaymobWebhookController;
use App\Http\Controllers\OrganizationWebhookController;
use App\Http\Controllers\OrganizationOnboardingController;
use App\Http\Controllers\OrganizationPaymentController;
use App\Http\Controllers\OrganizationCorporateEmailVerificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\MemberManagementController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Middleware\CheckOrganizationContext;

/*
|--------------------------------------------------------------------------
| Public Webhook & Billing Redirect Routes (No Middleware)
|--------------------------------------------------------------------------
*/

// Egypt Local Payment Gateway Webhook (Paymob)
Route::post('billing/paymob/webhook', PaymobWebhookController::class);

// Global B2B Gateway Webhook (Stripe Payment Success -> Atomic Identity Swap)
Route::post('billing/stripe/organization-webhook', [OrganizationWebhookController::class, 'handle']);

// Post-payment landing route for Paymob redirection
Route::get('billing/paymob/redirect', [UserSubscriptionController::class, 'handleRedirect']);

Route::get('organizations/corporate-email/verify/{billing_order}', [OrganizationCorporateEmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('organizations.corporate-email.verify');

// Public endpoint to retrieve dynamic subscription tier data
Route::get('/billing/plans', [SubscriptionBillingController::class, 'plans']);


/*
|--------------------------------------------------------------------------
| Public Authentication & Identity Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function() {
    Route::controller(AuthenticationController::class)->group(function() {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
        Route::post('/logout', 'logout')->middleware('auth:sanctum');
    });

    Route::controller(PasswordResetController::class)->group(function() {
        Route::post('/forgot-password', 'forgot');
        Route::post('/reset-password', 'reset');
    });

    // Required structural route named by Laravel's core password reset email dispatcher
    Route::get('/password/reset/{token}', function ($token) {
        return response()->json([
            'status' => 'success',
            'message' => 'Use this token to reset your password',
            'token' => $token
        ]);
    })->name('password.reset');

    // Authentication verification flow endpoints
    Route::middleware('auth:sanctum')->group(function() {
        Route::get('/me', function (Request $request) {
            return response()->json(['user' => $request->user()]);
        });
        Route::get('/status', function (Request $request) {
            return response()->json(['status' => 'success', 'user' => $request->user()]);
        });
    });

    // Time-Based One-Time Password (TOTP) 2FA Handlers
    Route::controller(TwoFactorAuthController::class)->group(function() {
        Route::post('/2fa/setup', 'setup')->middleware('auth:sanctum');
        Route::post('/2fa/enable', 'enable')->middleware('auth:sanctum');
        Route::post('/2fa/disable', 'disable')->middleware('auth:sanctum');
        Route::post('/2fa/verify', 'verify');
        Route::get('/2fa/status', 'status')->middleware('auth:sanctum');
    });

    // Google OAuth 2.0 Integration Handlers
    Route::controller(GoogleAuthController::class)->group(function() {
        Route::get('/google/redirect', 'redirect');
        Route::get('/google/callback', 'callback');
    });
});

/*
|--------------------------------------------------------------------------
| Cryptographically Signed Email Verification Protocol
|--------------------------------------------------------------------------
*/
Route::prefix('email')->group(function() {
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'sendEmailVerification'])
        ->middleware('signed')->name('verification.verify');
        
    Route::post('/verification-notification/resend', [EmailVerificationController::class, 'resendEmailVerification'])
        ->middleware(['throttle:6,1'])->name('verification.send');
});


/*
|--------------------------------------------------------------------------
| Authenticated Infrastructure Group (Sanctum Protected)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function() {

    /*
     * ── Group A: Context-Free Global Routes ─────────────────────────────────
     * These endpoints execute under general auth scope and do NOT demand 
     * structural or behavioral multi-tenant organization bounding headers.
     */

    // Individual Account Metrics & Global Security Overview
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics']);

    // User Profile Management — text fields (full_name, job_title)
    Route::match(['put', 'patch'], '/user/profile', [ProfileController::class, 'update']);

    // User Profile Management — avatar upload
    Route::post('/user/profile/avatar', [ProfileController::class, 'updateAvatar']);

    // Standard B2C Subscription & Payment Orders Management
    Route::controller(UserSubscriptionController::class)->group(function() {
        Route::get('subscription', 'show');
        Route::patch('subscription', 'update');
    });
    Route::controller(SubscriptionBillingController::class)->group(function() {
        Route::post('billing/checkout', 'checkout');
        Route::get('billing/orders', 'orders');
    });

    // B2B Organization Verification Onboarding Initialization
    Route::post('/organizations/initiate', [OrganizationOnboardingController::class, 'initiate']);

    // B2B Organization Paymob Payment Management
    Route::controller(OrganizationPaymentController::class)->group(function() {
        Route::post('/organizations/{organization_id}/payment/checkout', 'initiateCheckout');
        Route::get('/organizations/{organization_id}/payment/status', 'getPaymentStatus');
        Route::post('/organizations/{id}/resume-payment', 'resumePayment');
    });
    Route::post('/organizations/{organization_id}/corporate-email', [OrganizationCorporateEmailVerificationController::class, 'request']);
    
    // Critical Free Endpoint: Resolves workspaces current user belongs to populate the Frontend UI Switcher Dropdown
    Route::get('/organizations/my-workspaces', [OrganizationController::class, 'getMyWorkspaces']);

    Route::controller(OrganizationController::class)->group(function () {
        Route::post('/organizations/{id}/resend-verification', 'resendVerification');
        Route::post('/organizations/{id}/restore', 'restore');
        Route::delete('/organizations/{id}/force', 'forceDestroy');
        Route::delete('/organizations/{id}/pending', 'deletePending');
    });

    // Corporate Member Provisioning Endpoint for Registered Platform Users
    Route::post('/organizations/{token}/accept', [OrganizationInvitationController::class,'acceptExistingUser']);


    /*
     * ── Group B: Context-Bound B2B Tenant Isolation (Strict Architecture) ──
     * This sub-pipeline intercepts requests using 'CheckOrganizationContext'. 
     * Validates member attachment to 'X-Organization-Id' or throws a 403 Forbidden payload.
     */
    Route::middleware(CheckOrganizationContext::class)->group(function() {

        // Polymorphic Projects Isolation CRUD
        Route::apiResource('projects', ProjectController::class);

        // Active Tenant Metadata Control System
        Route::controller(OrganizationController::class)->group(function() {
            Route::get('/organizations/details', 'getOrgDetails');
            Route::put('/organizations', 'update');
            Route::patch('/organizations', 'update');
            Route::delete('/organizations', 'destroy'); // Cascades structural asset deletion via DB Transactions
        });

        // Identity & Access Management (IAM) Corporate Member Operations
        Route::controller(MemberManagementController::class)->group(function() {
            Route::get('/organizations/members', 'list');
            Route::get('/organizations/invitations', 'listInvitations');
            Route::post('/organizations/members/invite', 'invite'); // Validates subscription ceilings
            Route::put('/organizations/members/{userId}/role', 'updateRole'); // Immutability on Owner row protected
            Route::delete('/organizations/members/{userId}', 'remove'); // Forces instant Sanctum tokens revocation
        });

        // Legacy Project Collaboration & Invitations Engine
        Route::controller(ProjectInvitationController::class)->group(function() {
            Route::post('projects/{project}/invite', 'invite');
            Route::post('invitations/{token}/accept', 'accept');
            Route::get('invitations/{token}', 'showInvitation');
            Route::delete('invitations/{token}/reject', 'reject');
            Route::get('projects/{project}/invitations', 'pendingInvitation');
        });

        Route::controller(CollaboratorController::class)->group(function() {
            Route::delete('projects/{project}/collaborators/{user}', 'remove');
            Route::patch('projects/{project}/collaborators/{user}', 'changeRole');
            Route::get('/projects/{project}/collaborators', 'getAll');
        });

        // Context-aware Target Management Framework (Handles Auto/Manual DNS Ownership Validation State)
        Route::controller(TargetController::class)->group(function() {
            Route::post('projects/{project}/targets', 'addNewTarget');
            Route::get('targets/{target}', 'getTargetDetails');
            Route::delete('projects/{project}/targets/{target}', 'deleteTarget');
            Route::get('projects/{project}/targets', 'getAllTargets');
            Route::get('/targets', 'allTargets');
            Route::patch('projects/{project}/targets/{target}', 'updateTarget');
            Route::post('/targets/{target}/verify-dns', 'verifyDns');
        });

        // Automated Container-bound Orchestration Scanner Engine (Enforces ScanPolicy Tier Constraints)
        Route::controller(ScanController::class)->group(function() {
            Route::get('/scanners', 'getAvailableScanners');
            Route::post('/scan/start', 'startScan'); // Validates monthly threshold usage logs
            Route::get('/scan/{scanJobId}/status', 'getScanStatus');
            Route::get('/scan/{scanJobId}/findings', 'fetchFindings');
            Route::get('/projects/{project}/scans', 'projectScans');
            Route::get('/targets/{target}/scans', 'targetScans');
            Route::post('/scan/{scanJobId}/pause', 'pauseScan');
            Route::post('/scan/{scanJobId}/continue', 'continueScan');
            Route::post('/scan/{scanJobId}/cancel', 'cancelScan');
        });

        // Vulnerability Findings Repository Parsers
        Route::controller(FindingController::class)->group(function() {
            Route::get('/targets/{target}/findings', 'index');
            Route::get('/projects/{project}/findings', 'getProjectFindings');
            Route::patch('/findings/{finding}/status', 'updateStatus');
            Route::patch('/findings/{finding}/severity', 'updateSeverity');
            Route::post('/targets/{target}/findings', 'uploadFinding');
            Route::get('targets/{target}/endpoints', 'getEndpoints');
        });

        // Developer Programmatic API Credentials Storage
        Route::controller(UserApiKeyController::class)->group(function() {
            Route::get('/apiKeys', 'index');
            Route::post('/apiKeys', 'store');
            Route::delete('/apiKeys/{apiKey}', 'delete');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Public Organization Invitation Landing (Pre-auth Pipeline)
|--------------------------------------------------------------------------
*/
Route::controller(OrganizationInvitationController::class)->group(function() {
    Route::get('/organizations/invitations/{token}', 'getInvitationDetails');
    Route::post('/organizations/invitations/{token}/register', 'acceptNewUser'); // Pre-fills immutable company registration form
});
