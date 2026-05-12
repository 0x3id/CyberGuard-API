<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    use HasUuids;

    protected $fillable = [
        'scan_job_id',
        'target_id',
        'driver_id',
        'title',
        'description',
        'severity',
        'cvss_score',
        'cvss_vector',
        'cve_id',
        'remediation',
        'status',
        'affected_url',
        'proof',
        'metadata',
        'tags',
        'raw_data',
    ];

    protected $casts = [
        'cvss_score' => 'decimal:1',
        'metadata' => 'array',
        'tags' => 'array',
    ];

    public function scanJob(): BelongsTo
    {
        return $this->belongsTo(ScanJob::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => '#ff3d5a',
            'high'     => '#ff6b35',
            'medium'   => '#ffd600',
            'low'      => '#00ff9d',
            default    => '#7a9bb5',
        };
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
