<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

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
    public function resendEmailVerification(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if($user->hasVerifiedEmail()) {
            return response()->json(["status"=>"error", "message"=>"Email already verified"], 400);
        }
        $user->sendEmailVerificationNotification();
        return response()->json(["status"=>"success", "message"=>"Verification link sent!"], 200);
    }


}
