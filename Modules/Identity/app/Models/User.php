<?php

declare(strict_types=1);

namespace Modules\Identity\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasKsuid;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Modules\Identity\Database\Factories\UserFactory;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasKsuid, Notifiable;

    use HasRoles {
        hasPermissionTo as protected grantedPermissionTo;
    }

    /**
     * is_admin and denied_permissions are left out on purpose: each grants or
     * revokes privileges, so nothing request-shaped may reach them through
     * fill(). Set them explicitly, as UserController does.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_changed_at',
        'active',
        'locale',
        'avatar',
        'bio',
    ];

    /**
     * Mirrors the column default, so a freshly created model reads as active
     * before it is ever reloaded — EnsureUserIsActive would otherwise sign
     * out a user who registered a moment ago.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_admin' => 'boolean',
            'password_changed_at' => 'datetime',
            'active' => 'boolean',
            'denied_permissions' => 'array',
            'locale' => 'string',
        ];
    }

    /**
     * Laravel's default factory resolver only strips an "App\Models\" prefix,
     * so it can't find a factory for a model living under Modules\Identity —
     * point it at the real one explicitly.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getAvatarAttribute(): ?string
    {
        $avatar = $this->attributes['avatar'] ?? null;

        if (! $avatar) {
            return null;
        }

        // Serve avatar through the application proxy endpoint
        // This avoids issues with RustFS presigned URL host mismatches
        return url("/avatars/{$this->id}");
    }

    /**
     * Locale to render this user's notifications in. Null falls back to the
     * application locale, which is the case until they pick one.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->active === true;
    }

    /**
     * Deactivate user account
     */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * Activate user account
     */
    public function activate(): void
    {
        $this->active = true;
    }

    /**
     * Whether deactivating or deleting this user would leave nobody able to
     * reach /system. Inactive admins don't count: they cannot sign in.
     */
    public function isLastActiveAdmin(): bool
    {
        return $this->is_admin
            && $this->isActive()
            && static::query()->activeAdmins()->whereKeyNot($this->getKey())->doesntExist();
    }

    public function scopeActiveAdmins(Builder $query): void
    {
        $query->where('is_admin', true)->where('active', true);
    }

    /**
     * Case-insensitive on every driver, Postgres included.
     */
    public function scopeMatching(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query, string $term) => $query->where(
            fn (Builder $query) => $query
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('email', "%{$term}%"),
        ));
    }

    /**
     * Spatie's check, with denied_permissions taking precedence over anything
     * a role or a direct grant gives. Overriding it here rather than beside it
     * means every path agrees: Policies, `can:` middleware, `@can` and
     * Gate::allows() all reach Spatie through this method.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if ($this->deniedPermissionIds() !== []) {
            $permission = $this->filterPermission($permission, $guardName);

            if (in_array((int) $permission->getKey(), $this->deniedPermissionIds(), true)) {
                return false;
            }
        }

        return $this->grantedPermissionTo($permission, $guardName);
    }

    /**
     * Kept for existing Policies; identical to hasPermissionTo(), including
     * the PermissionDoesNotExist it throws for a permission nobody seeded.
     */
    public function checkPermission(string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    /**
     * Get all permissions the user actually has (excluding denied).
     */
    public function getActualPermissions(): Collection
    {
        $denied = $this->deniedPermissionIds();

        return $this->getAllPermissions()
            ->reject(fn ($permission) => in_array((int) $permission->getKey(), $denied, true))
            ->values();
    }

    /**
     * @return list<int>
     */
    private function deniedPermissionIds(): array
    {
        return array_values(array_map('intval', $this->denied_permissions ?? []));
    }
}
