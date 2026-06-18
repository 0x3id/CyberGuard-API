<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;


class Project extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'created_by',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'max_collaborators',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_collaborators')
                    ->withPivot('role', 'status', 'invited_by', 'invited_at', 'accepted_at')
                    ->withTimestamps();
    }

    public function activeCollaborators(): BelongsToMany
    {
        return $this->collaborators()->wherePivot('status', 'accepted');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    public function phishingCampaigns(): HasMany
    {
        return $this->hasMany(PhishingCampaign::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function hasAccess(string $userId): bool
    {
        if ($this->owner_type === Organization::class) {
            $organization = $this->owner;

            return $organization instanceof Organization && $organization->hasMember($userId);
        }

        if ($this->created_by === $userId || $this->owner_id === $userId) return true;

        return $this->activeCollaborators()
                    ->where('user_id', $userId)
                    ->exists();
    }

    public function getUserRole(string $userId): ?string
    {
        if ($this->owner_type === Organization::class) {
            $organization = $this->owner;

            return $organization instanceof Organization ? $organization->getMemberRole($userId) : null;
        }

        if ($this->created_by === $userId) return 'owner';

        $collab = $this->collaborators()
                        ->where('user_id', $userId)
                        ->wherePivot('status', 'accepted')
                        ->first();

        return $collab?->pivot->role;
    }

    public function findings(): HasManyThrough
    {
        return $this->hasManyThrough(
            Finding::class, // The final model you want to fetch
            Target::class,  // The intermediate model it goes through
            'project_id',   // Foreign key on the targets table
            'target_id',    // Foreign key on the findings table
            'id',           // Local key on the projects table
            'id'            // Local key on the targets table
        );
    }

    public function canAddCollaborator(): bool
    {
        return $this->activeCollaborators()->count() < $this->max_collaborators;
    }
}
