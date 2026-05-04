<?php

namespace App\Services\Paymob;

class PaymobTransactionHmac
{
    /**
     * Verify Paymob "TRANSACTION" callback payload (JSON body with `obj` + `hmac`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyTransaction(array $payload): bool
    {
        $secret = config('paymob.hmac_secret');
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $hmac = $payload['hmac'] ?? null;
        $obj = $payload['obj'] ?? null;
        if (! is_string($hmac) || ! is_array($obj)) {
            return false;
        }

        $order = $obj['order'] ?? null;
        if (! is_array($order)) {
            return false;
        }

        $orderId = $order['id'] ?? null;
        if (! is_int($orderId) && ! is_string($orderId)) {
            return false;
        }

        $source = $obj['source_data'] ?? [];
        if (! is_array($source)) {
            $source = [];
        }

        $concat =
            $this->scalar($obj['amount_cents'] ?? '')
            .$this->scalar($obj['created_at'] ?? '')
            .$this->scalar($obj['currency'] ?? '')
            .$this->boolString($obj['error_occured'] ?? false)
            .$this->boolString($obj['has_parent_transaction'] ?? false)
            .$this->scalar($obj['id'] ?? '')
            .$this->scalar($obj['integration_id'] ?? '')
            .$this->boolString($obj['is_3d_secure'] ?? false)
            .$this->boolString($obj['is_auth'] ?? false)
            .$this->boolString($obj['is_capture'] ?? false)
            .$this->boolString($obj['is_refunded'] ?? false)
            .$this->boolString($obj['is_standalone_payment'] ?? false)
            .$this->boolString($obj['is_voided'] ?? false)
            .$this->scalar($orderId)
            .$this->scalar($obj['owner'] ?? '')
            .$this->boolString($obj['pending'] ?? false)
            .$this->scalar($source['pan'] ?? '')
            .$this->scalar($source['sub_type'] ?? '')
            .$this->scalar($source['type'] ?? '')
            .$this->boolString($obj['success'] ?? false);

        $hash = hash_hmac('sha512', $concat, $secret);

        return hash_equals($hash, $hmac);
    }

    private function boolString(mixed $value): string
    {
        return $this->normalizeBool($value) ? 'true' : 'false';
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value === 'true' || $value === '1';
        }

        return (bool) $value;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
