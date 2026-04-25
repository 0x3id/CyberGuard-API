<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'plan',
        'status',
        'max_projects',
        'max_targets',
        'max_scans_per_month',
        'started_at',
        'expires_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
