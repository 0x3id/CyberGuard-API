<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhishingCampaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'status',
        'email_subject',
        'email_body',
        'phishing_url',
        'sender_name',
        'sender_email',
        'authorized_domain',
        'scheduled_at',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PhishingTarget::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PhishingEvent::class, 'campaign_id');
    }
}
