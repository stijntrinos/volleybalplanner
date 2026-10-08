<?php

namespace App\Models;

use App\Planner\Support\ClubRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use SensitiveParameter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $app_authentication_secret
 * @property array<int, string>|null $app_authentication_recovery_codes
 * @property bool $is_platform_admin
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Club> $clubs
 * @property-read int|null $clubs_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsPlatformAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasDefaultTenant, HasTenants, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /** @return BelongsToMany<Club, $this> */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class)->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'platform' => $this->isPlatformAdmin(),
            'club' => $this->isPlatformAdmin() || $this->clubs()->where('is_active', true)->exists(),
            default => false,
        };
    }

    /**
     * Platform admins see every club, including inactive ones, so they can
     * always step in; everyone else only sees the active clubs they belong to.
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isPlatformAdmin()) {
            return Club::query()->orderBy('name')->get();
        }

        return $this->clubs()->where('is_active', true)->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (!$tenant instanceof Club) {
            return false;
        }

        return $this->isPlatformAdmin()
            || $this->clubs()->whereKey($tenant->getKey())->where('is_active', true)->exists();
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->getTenants($panel)->first();
    }

    public function canImpersonate(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function canBeImpersonated(): bool
    {
        return !$this->is_platform_admin;
    }

    public function assignClubRole(Club $club, ClubRole $role): void
    {
        $this->withClubTeam($club, fn () => $this->syncRoles([$role->value]));
    }

    public function removeClubRoles(Club $club): void
    {
        $this->withClubTeam($club, fn () => $this->syncRoles([]));
    }

    public function hasClubRole(Club $club, ClubRole $role): bool
    {
        return $this->withClubTeam($club, fn (): bool => $this->hasRole($role->value));
    }

    public function clubRole(Club $club): ?ClubRole
    {
        return $this->withClubTeam($club, fn (): ?ClubRole => ClubRole::tryFrom((string) $this->getRoleNames()->first()));
    }

    /**
     * Runs the callback with spatie's team id set to the club, so role checks
     * and assignments only see that club's assignments.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withClubTeam(Club $club, callable $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($club->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<int, string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  array<int, string>|null  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /** Whether the account is protected beyond its password. */
    public function hasStrongAuthentication(): bool
    {
        return filled($this->app_authentication_secret) || $this->hasPasskeysEnabled();
    }

    /**
     * Whether this user has to set up two-factor authentication before going
     * on: platform admins always (config auth.require_platform_admin_mfa),
     * members of a club that requires it, unless a passkey already covers it.
     */
    public function mustSetUpStrongAuthentication(): bool
    {
        if ($this->hasStrongAuthentication()) {
            return false;
        }

        if ($this->isPlatformAdmin() && config('auth.require_platform_admin_mfa')) {
            return true;
        }

        return $this->clubs()->where('is_active', true)->where('require_strong_auth', true)->exists();
    }
}
