<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'target_id',
        'generated_by',
        'title',
        'type',
        'format',
        'file_url',
        'summary',
        'generated_at',
    ];

    protected $casts = [
        'summary'      => 'array',
        'generated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
