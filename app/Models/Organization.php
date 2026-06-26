<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphMany as MorphManyRelation;

class Organization extends Model
{
    use HasUuids, Prunable, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'company_domain',
        'email',
        'email_verified_at',
        'logo_url',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription?->status === 'active';
    }

    public function hasSuccessfulCheckout(): bool
    {
        return $this->billingOrders()->where('status', 'paid')->exists();
    }

    public function resolveOnboardingStep(): string
    {
        if (! $this->isEmailVerified()) {
            return 'PENDING_VERIFICATION';
        }

        if (! $this->hasSuccessfulCheckout()) {
            return 'PENDING_PAYMENT';
        }

        return 'ACTIVE';
    }

    /**
     * Soft-deleted organizations older than 30 days are eligible for automated pruning.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(30));
    }

    /**
     * Cascade-delete tenant assets before the organization row is permanently purged.
     */
    protected function pruning(): void
    {
        OrganizationInvitation::query()
            ->where('organization_id', $this->id)
            ->delete();

        $this->projects()->withTrashed()->forceDelete();
        $this->subscription()?->delete();

        SubscriptionBillingOrder::query()
            ->where('billable_type', self::class)
            ->where('billable_id', $this->id)
            ->delete();

        $this->members()->detach();
    }

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

    public function billingOrders(): MorphManyRelation
    {
        return $this->morphMany(SubscriptionBillingOrder::class, 'billable');
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
