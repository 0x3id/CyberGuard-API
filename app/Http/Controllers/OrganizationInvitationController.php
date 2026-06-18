<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationInvitationController extends Controller
{
    /**
     * Get details of an invitation (Public endpoint).
     */
    public function getInvitationDetails(string $token)
    {
        $invitation = OrganizationInvitation::with('organization')
            ->where('token', $token)
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired invitation token.'], 404);
        }

        $user = User::where('email', $invitation->email)->first();
        $isExist = $user ? true : false;

        return response()->json([
            'status' => 'success',
            'is_exist' => $isExist,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization' => [
                    'name' => $invitation->organization->name,
                    'logo_url' => $invitation->organization->logo_url
                ]
            ]
        ]);
    }

    /**
     * Accept invitation for an EXISTING authenticated user.
     */
    public function acceptExistingUser(Request $request, string $token)
    {
        $user = $request->user();

        $invitation = OrganizationInvitation::where('token', $token)->first();

        if (!$invitation || $invitation->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired invitation token.'], 404);
        }

        if ($user->email !== $invitation->email) {
            return response()->json(['status' => 'error', 'message' => 'This invitation was sent to a different email address.'], 403);
        }

        try {
            DB::beginTransaction();

            $organization = $invitation->organization;

            if (!$organization->hasMember($user->id)) {
                $organization->members()->attach($user->id, [
                    'id' => Str::uuid(),
                    'role' => $invitation->role,
                    'joined_at' => now()
                ]);
            }

            $invitation->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully joined the organization.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Internal server error while joining.'], 500);
        }
    }

    /**
     * Accept invitation and register a NEW user (Public endpoint).
     */
    public function acceptNewUser(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)->first();

        if (!$invitation || $invitation->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired invitation token.'], 404);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'password' => 'required|min:6|confirmed|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
            'password_confirmation' => 'required|same:password',
            'job_tittle' => 'required|min:3|max:255|string',
        ]);

        try {
            DB::beginTransaction();

            // Create the new user
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $invitation->email,
                'job_tittle' => $validated['job_tittle'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // Auto-verify since they verified the invite link
            ]);

            $organization = $invitation->organization;

            // Attach to organization
            $organization->members()->attach($user->id, [
                'id' => Str::uuid(),
                'role' => $invitation->role,
                'joined_at' => now()
            ]);

            $invitation->delete();

            // Generate an access token for instant login
            $accessToken = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Account created and successfully joined the organization.',
                'token' => $accessToken,
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Internal server error while registering.'], 500);
        }
    }
}
