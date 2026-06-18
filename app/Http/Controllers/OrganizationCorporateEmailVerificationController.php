<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SubscriptionBillingOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Jobs\SendOrganizationEmailVerificationJob;

class OrganizationCorporateEmailVerificationController extends Controller
{
    public function request(Request $request, string $organization_id): JsonResponse
    {
        $validated = $request->validate([
            'corporate_email' => 'required|email|max:255',
        ]);

        
        $organization = Organization::query()->find($organization_id);
        if (! $organization) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found.'], 404);
        }

        if ($organization->owner_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Only the organization owner can verify the corporate email.'], 403);
        }

        if (! $organization->subscription || $organization->subscription->status !== 'pending_email_verification') {
            return response()->json(['status' => 'error', 'message' => 'Organization is not waiting for corporate email verification.'], 422);
        }

        $corporateEmail = strtolower($validated['corporate_email']);
        $domain = strtolower((string) Str::after($corporateEmail, '@'));
        if ($domain !== strtolower((string) $organization->domain)) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email domain must match the organization domain.'], 422);
        }

        $existingUser = User::query()
            ->where('email', $corporateEmail)
            ->whereKeyNot($request->user()->id)
            ->first();

        if ($existingUser) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email is do\'nt correct please change it.'], 409);
        }

        $order = SubscriptionBillingOrder::query()
            ->where('billable_type', Organization::class)
            ->where('billable_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        if (! $order) {
            return response()->json(['status' => 'error', 'message' => 'No paid organization billing order was found.'], 422);
        }

        $order->update([
            'pending_corporate_email' => $corporateEmail,
            'corporate_verification_sent_at' => now(),
        ]);

        // $verificationUrl = URL::temporarySignedRoute(
        //     'organizations.corporate-email.verify',
        //     now()->addHours(24),
        //     [
        //         'billing_order' => $order->id,
        //         'email' => $corporateEmail,
        //     ]
        // );

        // Mail::raw(
        //     "Confirm your CyberGuard organization email for {$organization->name}:\n\n{$verificationUrl}\n\nThis link expires in 24 hours.",
        //     fn ($message) => $message
        //         ->to($corporateEmail)
        //         ->subject('Confirm your CyberGuard organization email')
        // );

        SendOrganizationEmailVerificationJob::dispatch($request->user(), $order->id, $corporateEmail);

        return response()->json([
            'status' => 'success',
            'message' => 'Corporate email verification link sent.',
        ]);
    }

    public function verify(Request $request, SubscriptionBillingOrder $billing_order): JsonResponse
    {
        $email = strtolower((string) $request->query('email'));

        if ($billing_order->workspace_type !== 'organization' || $billing_order->status !== 'paid') {
            return response()->json(['status' => 'error', 'message' => 'Invalid organization billing order.'], 422);
        }

        if (! $billing_order->pending_corporate_email || strtolower($billing_order->pending_corporate_email) !== $email) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email does not match this verification request.'], 422);
        }

        /** @var Organization|null $organization */
        $organization = $billing_order->billable;
        if (! $organization instanceof Organization || ! $organization->subscription) {
            return response()->json(['status' => 'error', 'message' => 'Organization not found for this billing order.'], 404);
        }

        $domain = strtolower((string) Str::after($email, '@'));
        if ($domain !== strtolower((string) $organization->domain)) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email domain does not match the organization domain.'], 422);
        }

        $owner = User::query()->find($organization->owner_id);
        if (! $owner) {
            return response()->json(['status' => 'error', 'message' => 'Organization owner account not found.'], 404);
        }

        $existingUser = User::query()
            ->where('email', $email)
            ->whereKeyNot($owner->id)
            ->first();

        if ($existingUser) {
            return response()->json(['status' => 'error', 'message' => 'Corporate email is already used by another account.'], 409);
        }

        DB::transaction(function () use ($billing_order, $organization, $owner, $email): void {
            $months = max(1, (int) config('paymob.billing_period_months', 1));
            $startsFrom = $organization->subscription->expires_at?->isFuture()
                ? $organization->subscription->expires_at
                : now();

            $organization->subscription->update([
                'status' => 'active',
                'expires_at' => $startsFrom->copy()->addMonths($months),
            ]);

            $organization->members()->syncWithoutDetaching([
                $owner->id => [
                    'id' => (string) Str::uuid(),
                    'role' => 'owner',
                    'joined_at' => now(),
                ],
            ]);

            $owner->forceFill([
                'email' => $email,
                'email_verified_at' => now(),
            ])->save();

            $billing_order->update([
                'corporate_email_verified_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Corporate email verified and organization activated.',
        ]);
    }
}
