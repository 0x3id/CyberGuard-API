<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'target_id',
        'scan_job_id',
        'overall_score',
        'critical_count',
        'high_count',
        'medium_count',
        'low_count',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'overall_score' => 'decimal:2',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function scanJob(): BelongsTo
    {
        return $this->belongsTo(ScanJob::class);
    }
}
