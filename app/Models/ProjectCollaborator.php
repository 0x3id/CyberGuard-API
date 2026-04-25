<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCollaborator extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
        'status',
        'invited_by',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'invited_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
