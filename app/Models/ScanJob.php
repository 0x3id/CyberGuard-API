<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ScanJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'target_id',
        'project_id',
        'triggered_by',
        'scan_type',
        'status',
        'container_id',
        'started_at',
        'finished_at',
        'error_log',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(ScanModule::class, 'scan_job_modules')
                    ->withPivot('status', 'duration_ms')
                    ->withTimestamps();
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function riskScore(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->finished_at) return null;
        return $this->started_at->diffForHumans($this->finished_at, true);
    }
}
