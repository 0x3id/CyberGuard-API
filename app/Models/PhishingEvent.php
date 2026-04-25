<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhishingEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'phishing_target_id',
        'campaign_id',
        'event_type',
        'ip_address',
        'user_agent',
        'submitted_data',
        'occurred_at',
    ];

    protected $casts = [
        'submitted_data' => 'encrypted:array',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(PhishingTarget::class, 'phishing_target_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PhishingCampaign::class);
    }
}
