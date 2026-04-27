<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Jobs\SendPasswordResetEmail;

class PasswordResetController extends Controller
{
    /**
     * Send a reset password link to user email.
     */
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        SendPasswordResetEmail::dispatch($request->email);

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset request queued. Please check your email shortly.'
        ], 202);
    }

    /**
     * Reset password using token.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|confirmed|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => __($status)
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully.'
        ], 200);
    }
}
