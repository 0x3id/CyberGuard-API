<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SubscriptionBillingOrder extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'billable_type',
        'billable_id',
        'workspace_type',
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
        'pending_corporate_email',
        'corporate_email_verified_at',
        'corporate_verification_sent_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'last_paymob_payload' => 'array',
        'corporate_email_verified_at' => 'datetime',
        'corporate_verification_sent_at' => 'datetime',
    ];

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
