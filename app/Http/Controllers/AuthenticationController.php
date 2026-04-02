<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerifyMail;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

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
                'job_title' => $validated['job_title'],
                'full_name' => $validated['full_name'],
            ]);

            if($user)
            {
//                Mail::to($validated['email'])->send(new VerifyMail());
                $user->sendEmailVerificationNotification();
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
        
        // Check if user exists and password is correct
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password'
            ], 401);
        }
        
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
