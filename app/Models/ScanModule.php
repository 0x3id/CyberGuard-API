<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ScanModule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'config',
        'status',
    ];

    public function scanJobs(): BelongsToMany
    {
        return $this->belongsToMany(ScanJob::class, 'scan_job_modules')
                    ->withPivot('status', 'duration_ms')
                    ->withTimestamps();
    }
}
