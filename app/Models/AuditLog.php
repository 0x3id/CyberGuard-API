<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'owner_type',
        'owner_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
