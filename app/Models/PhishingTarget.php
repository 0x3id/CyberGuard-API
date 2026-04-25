<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhishingTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'employee_email',
        'employee_name',
        'department',
        'tracking_token',
        'sent_at',
        'awareness_score',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PhishingCampaign::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PhishingEvent::class);
    }
}
