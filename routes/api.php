<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\EmailVerificationController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


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
});

/**
 * Email Verify Routes 
 */
// - Send Email Verification
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'sendEmailVerification'])
        ->middleware('signed')->name('verification.verify');
// - Resend Email Verification
Route::post('/email/verification-notification/{id}', [EmailVerificationController::class, 'resendEmailVerification'])
        ->middleware(['throttle:6,1'])->name('verification.send');




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
