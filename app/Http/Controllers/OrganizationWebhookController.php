<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Exceptions\DomainValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationWebhookController extends Controller
{
    /**
     * Handle the incoming Stripe Webhook for organization onboarding.
     */
    public function handle(Request $request)
    {
        // Typically we would verify Stripe signature here.
        
        $payload = $request->all();
        $type = $payload['type'] ?? '';

        if ($type === 'invoice.paid' || $type === 'checkout.session.completed') {
            return $this->processOnboarding($payload);
        }

        return response()->json(['status' => 'ignored']);
    }

    private function processOnboarding(array $payload)
    {
        // Extract metadata from payload (e.g. from checkout.session.completed)
        $metadata = $payload['data']['object']['metadata'] ?? [];
        
        $userId = $metadata['user_id'] ?? null;
        $organizationId = $metadata['organization_id'] ?? null;
        $companyEmail = $metadata['company_email'] ?? null;
        $companyDomain = $metadata['company_domain'] ?? null;

        if (!$userId || !$organizationId || !$companyEmail || !$companyDomain) {
            return response()->json(['status' => false, 'message' => 'Missing metadata'], 400);
        }

        // Domain Matching Validation Check
        $emailDomain = substr(strrchr($companyEmail, "@"), 1);
        if ($emailDomain !== $companyDomain) {
            throw new DomainValidationException("The provided company email domain does not match the organization domain.");
        }

        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $organization = Organization::findOrFail($organizationId);

            // 1. Update subscription to 'active'
            if ($organization->subscription) {
                $organization->subscription->update(['status' => 'active']);
            }

            // 2. Swap user's primary email
            $user->update([
                'email' => $companyEmail,
                'email_verified_at' => now(), // Assuming it's verified since they paid
            ]);

            // 3. Populate organization_members table as 'owner'
            $organization->members()->syncWithoutDetaching([
                $user->id => [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'role' => 'owner',
                    'joined_at' => now()
                ]
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Identity swap and organization onboarding completed successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process organization onboarding webhook: " . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Internal server error while processing webhook.'
            ], 500);
        }
    }
}
