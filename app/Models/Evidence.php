<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasUuids;

    protected $fillable = [
        'finding_id',
        'type',
        'file_url',
        'mime_type',
        'size_bytes',
        'uploaded_at',
    ];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
