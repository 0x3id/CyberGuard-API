<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionBillingOrder extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'plan',
        'amount_cents',
        'currency',
        'status',
        'merchant_reference',
        'paymob_order_id',
        'paymob_transaction_id',
        'paid_at',
        'failure_reason',
        'last_paymob_payload',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'last_paymob_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
