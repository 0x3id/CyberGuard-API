<?php

namespace App\Services\Paymob;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymobClient
{
    public function authToken(): string
    {
        $apiKey = config('paymob.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Paymob API key is not configured.');
        }

        $response = Http::baseUrl(config('paymob.base_url'))
            ->acceptJson()
            ->asJson()
            ->post('/auth/tokens', ['api_key' => $apiKey])
            ->throw();

        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Paymob auth response missing token.');
        }

        return $token;
    }

    /**
     * @param  array<int, array{name: string, amount_cents: int, description: string, quantity: string}>  $items
     * @return array{id: int, merchant_order_id?: string|null}
     */
    public function registerOrder(
        string $authToken,
        int $amountCents,
        string $currency,
        array $items,
        ?string $merchantOrderId = null,
    ): array {
        $payload = [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'items' => $items,
        ];

        if ($merchantOrderId !== null && $merchantOrderId !== '') {
            $payload['merchant_order_id'] = $merchantOrderId;
        }

        $response = Http::baseUrl(config('paymob.base_url'))
            ->acceptJson()
            ->asJson()
            ->post('/ecommerce/orders', $payload);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Paymob order registration failed: '.$response->body(),
                previous: $e
            );
        }

        $id = $response->json('id');
        if (! is_int($id)) {
            throw new RuntimeException('Paymob order response missing id.');
        }

        return [
            'id' => $id,
            'merchant_order_id' => $response->json('merchant_order_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $billingData
     */
    public function createPaymentKey(
        string $authToken,
        int $amountCents,
        int $paymobOrderId,
        string $currency,
        array $billingData,
    ): string {
        $integrationId = config('paymob.integration_id');
        if ($integrationId === null || $integrationId === '') {
            throw new RuntimeException('Paymob integration id is not configured.');
        }

        $payload = [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'billing_data' => $billingData,
            'currency' => $currency,
            'integration_id' => (int) $integrationId,
        ];

        $response = Http::baseUrl(config('paymob.base_url'))
            ->acceptJson()
            ->asJson()
            ->post('/acceptance/payment_keys', $payload);

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Paymob payment key request failed: '.$response->body(),
                previous: $e
            );
        }

        $token = $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Paymob payment key response missing token.');
        }

        return $token;
    }

    public function iframeUrl(string $paymentToken): string
    {
        $iframeId = config('paymob.iframe_id');
        if ($iframeId === null || $iframeId === '') {
            throw new RuntimeException('Paymob iframe id is not configured.');
        }

        $base = rtrim((string) config('paymob.base_url'), '/');

        return $base.'/acceptance/iframes/'.rawurlencode((string) $iframeId)
            .'?payment_token='.rawurlencode($paymentToken);
    }
}
