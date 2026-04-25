<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

/**
 * @method static create(array $array)
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasUuids, SoftDeletes, Notifiable;

    protected $fillable = [
        'name',
        'full_name',
        'email',
        'password',
        'avatar_url',
        'two_factor_enabled',
        'failed_login_attempts',
        'lockout_until',
        'ip_address',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'last_login_at'       => 'datetime',
        'two_factor_enabled'  => 'boolean',
        'failed_login_attempts' => 'integer',
        'lockout_until'       => 'datetime',
        'password'            => 'hashed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token)
    {
        // New Notification
        $this->notify(new ResetPasswordNotification($token));
    }

    // ── Relationships ──────────────────────────────────────

    // User → له subscription شخصية واحدة
    public function subscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class);
    }

    // User → ينتمي لـ Organizations كتير (عن طريق organization_members)
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
                    ->withPivot('role', 'joined_at')
                    ->withTimestamps();
    }

    // User → عنده Projects شخصية (owner_type = 'App\Models\User')
    public function personalProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    // User → مشارك في Projects ناس تانية
    public function collaboratingProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_collaborators')
                    ->withPivot('role', 'status', 'invited_at', 'accepted_at')
                    ->wherePivot('status', 'accepted');
    }

    // ── Helper Methods ─────────────────────────────────────

    // هل المستخدم عنده باقة Pro أو أعلى؟
    public function hasPro(): bool
    {
        return $this->subscription
            && in_array($this->subscription->plan, ['pro', 'enterprise'])
            && $this->subscription->status === 'active';
    }

    // هل وصل للـ Limit بتاعه في المشاريع؟
    public function canCreateProject(): bool
    {
        $limit   = $this->subscription?->max_projects ?? 3;
        $current = $this->personalProjects()->count();
        return $current < $limit;
    }
}
