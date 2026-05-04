<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerifyMail;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use App\Jobs\SendEmailVerifyJob;

class AuthenticationController extends Controller
{
/*===================== Register Controller ============================*/
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
        if($validated)
        {
            $data = ['status' => 'success', 'message' => 'Registered successfully', 'data' => $validated];
            $user = User::query()->create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'job_tittle' => $validated['job_tittle'],
                'full_name' => $validated['full_name'],
                'ip_address' => $request->ip(),
            ]);

            UserSubscription::query()->create([
                'user_id' => $user->id,
                'plan' => 'free',
                'status' => 'active',
                'max_projects' => env('FREE_MAX_PROJECTS'),
                'max_targets' => env('FREE_MAX_TARGETS'),
                'max_scans_per_month' => env('FREE_MAX_SCANS_PER_MONTH'),
                'started_at' => now(),
            ]);
            
            if($user)
            {
                // Mail::to($validated['email'])->send(new VerifyMail());
                // $user->sendEmailVerificationNotification();
                SendEmailVerifyJob::dispatch($user);
                unset($data['data']['password']);
                unset($data['data']['password_confirmation']);
                unset($data['data']['avatar']);
                return response()->json($data, 201);
            }
        }
    }
/*===================== Login Controller ================================*/
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        
        // Find user by email
        $user = User::where('email', $validated['email'])->first();

        // If no user, we cannot increment counter (avoid user enumeration), return generic message
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password'
            ], 401);
        }

        // Lockout check
        if ($user->lockout_until && now()->lessThan($user->lockout_until)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account locked due to too many failed login attempts. Try again at ' . $user->lockout_until->toDateTimeString()
            ], 423);
        }

        // Check password
        if (!Hash::check($validated['password'], $user->password)) {
            $user->failed_login_attempts = $user->failed_login_attempts + 1;
            $user->ip_address = $request->ip();

            if ($user->failed_login_attempts >= 5) {
                $user->lockout_until = now()->addMinutes(15);
            }

            $user->save();

            $message = 'Invalid email or password';
            if ($user->failed_login_attempts >= 5) {
                $message = 'Account locked due to too many failed login attempts. Retry after 15 minutes.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $message
            ], 401);
        }

        // Reset lockout info on successful login
        $user->failed_login_attempts = 0;
        $user->lockout_until = null;
        $user->ip_address = $request->ip();
        $user->save();
        
        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address'
            ], 401);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_enabled) {
            return response()->json([
                'status' => 'success',
                'message' => '2FA verification required',
                'data' => [
                    'requires_2fa' => true,
                    'email' => $user->email
                ]
            ], 200);
        }
        
        // Create API token (using Sanctum)
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Update last login at
        $user->last_login_at = now();
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'requires_2fa' => false,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'job_title' => $user->job_title,
                ],
                'token' => $token
            ]
        ], 200);
    }
/*===================== Logout Controller ================================*/
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(["status"=>"success", "message"=>"Logged out successfully"]);
    }
}
