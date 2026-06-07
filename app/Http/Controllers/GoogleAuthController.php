<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * GoogleAuthController (Socialite Edition)
 *
 * Implements Sign in with Google using Laravel Socialite's Google driver.
 * All OAuth mechanics (state generation, CSRF verification, code exchange,
 * token refresh, and profile fetching) are handled by Socialite internally.
 *
 * Because this is a pure stateless JSON API (no web sessions), we use
 * Socialite's `stateless()` mode — which skips Laravel's session-based
 * state check and is the correct approach for decoupled SPA / mobile clients.
 *
 * Flow:
 *  1. GET /api/auth/google/redirect
 *     → Returns the Google consent page URL as JSON.
 *       The frontend redirects the user's browser there.
 *
 *  2. GET /api/auth/google/callback
 *     → Socialite exchanges the code for a token and fetches the user profile.
 *       We then find or create the user in the DB, issue a Sanctum token,
 *       and return it as JSON.
 */
class GoogleAuthController extends Controller
{
    /*==========================================================================
    | Step 1: Return the Google Authorization URL
    |
    | Socialite builds the full URL (client_id, scope, redirect_uri, state, etc.)
    | from config/services.php automatically. We extract the target URL from
    | the redirect response and return it as JSON for the frontend to use.
    |=========================================================================*/
    public function redirect(): JsonResponse
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $googleProvider */
        $googleProvider = Socialite::driver('google');

        $url = $googleProvider
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'status'       => 'success',
            'redirect_url' => $url,
        ]);
    }

    /*==========================================================================
    | Step 2: Handle Google Callback and Issue Sanctum Token
    |
    | Socialite automatically:
    |   - Verifies the authorization code is present
    |   - POSTs to Google's token endpoint to exchange the code for a token
    |   - GETs the user profile from Google's UserInfo API
    |
    | We then apply our 3-case find-or-create logic and return a Sanctum token.
    |=========================================================================*/
    public function callback()
    {
        /*----------------------------------------------------------------------
        | 2a. Let Socialite handle the entire OAuth handshake.
        |     It will throw an exception if the code is missing, expired,
        |     or if Google returns an error — we catch that below.
        |---------------------------------------------------------------------*/
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $googleProvider */
            $googleProvider = Socialite::driver('google');

            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = $googleProvider
                ->stateless()
                ->user();

        } catch (\Exception $e) {
            Log::error('Google OAuth: Socialite failed to retrieve user', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Google authentication failed. The code may have expired or been already used. Please try again.',
            ], 422);
        }

        /*----------------------------------------------------------------------
        | 2b. Validate the minimum required fields from Google.
        |     Socialite normalises the profile, but we guard defensively.
        |---------------------------------------------------------------------*/
        if (! $googleUser->getId() || ! $googleUser->getEmail()) {
            Log::error('Google OAuth: incomplete profile data from Socialite', [
                'id'    => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Google profile is incomplete. Ensure your account has a verified email.',
            ], 422);
        }

        /*----------------------------------------------------------------------
        | 2c. Find or Create the user in the database.
        |
        | Priority:
        |  1. Match by google_id            → returning Google user
        |  2. Match by email (no google_id) → existing local user, link account
        |  3. No match                      → register brand-new user
        |---------------------------------------------------------------------*/
        $googleId = $googleUser->getId();
        $email    = $googleUser->getEmail();
        $name     = $googleUser->getName()   ?? $email;
        $avatar   = $googleUser->getAvatar() ?? null;

        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // Check for an existing local account with the same email
            $user = User::where('email', $email)->first();

            if ($user) {
                // ── Link existing local account to Google (account merging) ──
                $user->google_id     = $googleId;
                $user->auth_provider = 'google';
                $user->avatar_url    = $user->avatar_url ?? $avatar;

                // Trust Google's email verification for unverified local accounts
                if (! $user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();

            } else {
                // ── Register a brand-new Google-only user (no password) ──
                $user = User::create([
                    'google_id'         => $googleId,
                    'auth_provider'     => 'google',
                    'full_name'         => $name,
                    'email'             => $email,
                    'password'          => null,
                    'avatar_url'        => $avatar,
                    'email_verified_at' => now(), // Google already verified this email
                ]);

                // Provision the default free subscription for new users
                UserSubscription::create([
                    'user_id'             => $user->id,
                    'plan'                => 'free',
                    'status'              => 'active',
                    'max_projects'        => env('FREE_MAX_PROJECTS', 3),
                    'max_targets'         => env('FREE_MAX_TARGETS', 5),
                    'max_scans_per_month' => env('FREE_MAX_SCANS_PER_MONTH', 10),
                    'started_at'          => now(),
                ]);
            }
        } else {
            // ── Returning Google user: refresh avatar in case it changed ──
            $user->avatar_url    = $avatar ?? $user->avatar_url;
            $user->last_login_at = now();
            $user->save();
        }

        /*----------------------------------------------------------------------
        | 2d. Issue a Sanctum API token and return it to the client.
        |     Revoke any previous Google OAuth tokens to keep the table clean.
        |---------------------------------------------------------------------*/
        $user->tokens()->where('name', 'google-oauth-token')->delete();

        $token = $user->createToken('google-oauth-token')->plainTextToken;

        $user->last_login_at = now();
        $user->save();

        Log::info('Google OAuth: login successful via Socialite', [
            'user_id'  => $user->id,
            'email'    => $user->email,
            'provider' => $user->auth_provider,
        ]);

        return redirect()->away(env('FRONTEND_URL') . 'google-callback?token=' . $token);
    }
}
