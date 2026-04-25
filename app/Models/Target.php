<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'type',
        'value',
        'label',
        'is_verified',
        'risk_score',
        'last_scanned_at',
    ];

    protected $casts = [
        'is_verified'     => 'boolean',
        'last_scanned_at' => 'datetime',
        'risk_score'      => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scanJobs(): HasMany
    {
        return $this->hasMany(ScanJob::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }

    public function latestScan(): ?ScanJob
    {
        return $this->scanJobs()->latest()->first();
    }

    public function openFindingsCount(): int
    {
        return $this->findings()->where('status', 'open')->count();
    }
}
