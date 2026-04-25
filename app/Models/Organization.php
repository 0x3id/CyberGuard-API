<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organization extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'domain',
        'logo_url',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
                    ->withPivot('role', 'joined_at')
                    ->withTimestamps();
    }

    public function projects(): MorphMany
    {
        return $this->morphMany(Project::class, 'owner');
    }

    public function hasMember(string $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function getMemberRole(string $userId): ?string
    {
        $member = $this->members()->where('user_id', $userId)->first();
        return $member?->pivot->role;
    }
}
