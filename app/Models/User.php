<?php

namespace App\Models;

use App\Planner\Support\ClubRole;
use Database\Factories\UserFactory;
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
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
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
}
