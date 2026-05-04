<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserSubscriptionRequest;
use App\Support\SubscriptionPlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription;

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'No subscription found for this user.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $subscription,
        ]);
    }

    public function update(UpdateUserSubscriptionRequest $request): JsonResponse
    {
        $subscription = $request->user()->subscription;

        if (! $subscription) {
            return response()->json([
                'status' => 'error',
                'message' => 'No subscription found for this user.',
            ], 404);
        }

        $plan = $request->validated('plan');
        $limits = SubscriptionPlanLimits::forPlan($plan);

        $subscription->fill([
            'plan' => $plan,
            'status' => 'active',
            'max_projects' => $limits['max_projects'],
            'max_targets' => $limits['max_targets'],
            'max_scans_per_month' => $limits['max_scans_per_month'],
            'expires_at' => null,
        ]);
        $subscription->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Subscription updated.',
            'data' => $subscription->fresh(),
        ]);
    }

    public function handleRedirect(Request $request)
    {
        // 1. Capture the parameters from the URL
        $status_raw = $request->query('success'); // "true" or "false"
        $isSuccess = ($status_raw === 'true');

        // 2. Format transactional data
        $transactionId = $request->query('id', 'N/A');
        $orderId = $request->query('order', 'N/A');
        $amountRaw = $request->query('amount_cents', 0);
        $amount = number_format($amountRaw / 100, 2); // Convert cents to EGP
        $currency = $request->query('currency', 'EGP');
        
        // 3. Prepare display messages and status icon type
        if ($isSuccess) {
            $statusType = 'success';
            $mainTitle = 'Payment Approved!';
            $subTitle = 'Thank you for your business. Your order is now processing.';
            $message = 'Payment was captured successfully.';
        } else {
            $statusType = 'failed';
            $mainTitle = 'Payment Failed.';
            $subTitle = 'We could not complete your transaction. Please check your details.';
            // If there's a specific error message from Paymob, you can pass it here
            $message = $request->query('txn_response_code', 'Rejected by provider.'); 
        }

        // 4. Capture card details (for the status block)
        $cardType = $request->query('source_data_sub_type', 'Card');
        $cardLast4 = $request->query('source_data_pan', '****');

        // 5. Send it all to the Blade view
        return view('payment-status', [
            'isSuccess'    => $isSuccess,
            'statusType'   => $statusType,
            'mainTitle'    => $mainTitle,
            'subTitle'     => $subTitle,
            'transactionId' => $transactionId,
            'amount'       => $amount,
            'currency'     => $currency,
            'cardType'     => $cardType,
            'cardLast4'    => $cardLast4
        ]);
    }
}
