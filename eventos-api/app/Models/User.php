<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Global user account — platform staff + organizer members. Org binding lives
 * in memberships; this model is NOT tenant-scoped (architecture §6.1).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_staff' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function isPlatformStaff(): bool
    {
        return (bool) $this->is_platform_staff;
    }

    /**
     * Does this user hold the given permission key in the active organization?
     * Mirrors the `perm:` middleware (EnsurePermission) so services/resources can
     * make the same RBAC decision. Platform staff bypass. Falls back to the
     * ambient TenantContext when no org id is passed.
     */
    public function hasPermission(string $permission, ?int $organizationId = null): bool
    {
        if ($this->isPlatformStaff()) {
            return true;
        }

        $organizationId ??= app(\App\Support\Tenancy\TenantContext::class)->id();
        if (! $organizationId) {
            return false;
        }

        return Membership::where('organization_id', $organizationId)
            ->where('user_id', $this->id)
            ->first()?->roles()
            ->whereHas('permissions', fn ($q) => $q->where('key', $permission))
            ->exists() ?? false;
    }
}
