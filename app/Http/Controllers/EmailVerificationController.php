<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Jobs\SendEmailVerifyJob;

class EmailVerificationController extends Controller
{

    //Send Email Verification
    public function sendEmailVerification(Request $request)
    {
        $user = User::findOrFail($request->id);

        if (! hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link'
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    //Resend Email Verification
    public function resendEmailVerification(Request $request) : JsonResponse
    {
        // 1. Validate email address
        $email = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $email)->first();
        // 2. Ckeck if user is register
        if(!$user)
        {
            return response()->json(["status"=>"error", "message"=>"Please register"], 403);
        }

        // 3. Ckeck if email not verified
        if($user->hasVerifiedEmail())
        {
            return response()->json(["status"=>"error", "message"=>"Email already verified"], 400);
        }

        // 4. Dispatch Job to send email
        SendEmailVerifyJob::dispatch($user);
        return response()->json(["status"=>"success", "message"=>"Verification link sent!"], 200);
    }


}
