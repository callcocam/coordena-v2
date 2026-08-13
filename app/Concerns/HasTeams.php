<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Enums\TeamPermission;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Team;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Cached effective permission names keyed by team id.
     *
     * @var array<string, Collection<int, string>>
     */
    protected array $cargoPermissionCache = [];

    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['is_owner'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.is_owner', true);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get all cargos assigned to the user, across every team.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function cargos(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot(['team_id']);
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return (bool) $this->teamMemberships()
            ->where('team_id', $team->id)
            ->value('is_owner');
    }

    /**
     * Get the cargos the user holds on the given team.
     *
     * @return Collection<int, Role>
     */
    public function cargosForTeam(Team $team): Collection
    {
        return $this->cargos()
            ->wherePivot('team_id', $team->id)
            ->get();
    }

    /**
     * Determine if the user holds the cargo with the given key on the team.
     */
    public function hasCargo(Team $team, string $key): bool
    {
        return $this->cargosForTeam($team)->contains('key', $key);
    }

    /**
     * Assign a cargo to the user on the given team.
     */
    public function assignCargo(Team $team, Role|string $cargo): void
    {
        $role = $cargo instanceof Role ? $cargo : $this->resolveCargo($team, $cargo);

        if ($role === null) {
            return;
        }

        $alreadyAssigned = $this->cargos()
            ->wherePivot('team_id', $team->id)
            ->where('roles.id', $role->id)
            ->exists();

        if (! $alreadyAssigned) {
            $this->cargos()->attach($role->id, ['team_id' => $team->id]);
        }

        $this->forgetCargoCache($team);
    }

    /**
     * Remove a cargo from the user on the given team.
     */
    public function removeCargo(Team $team, Role|string $cargo): void
    {
        $role = $cargo instanceof Role ? $cargo : $this->resolveCargo($team, $cargo);

        if ($role === null) {
            return;
        }

        $this->cargos()
            ->wherePivot('team_id', $team->id)
            ->detach($role->id);

        $this->forgetCargoCache($team);
    }

    /**
     * Replace the user's cargos on the given team with the given keys.
     *
     * @param  list<string>  $keys
     */
    public function syncCargos(Team $team, array $keys): void
    {
        $this->cargos()
            ->wherePivot('team_id', $team->id)
            ->detach();

        foreach (array_unique($keys) as $key) {
            $role = $this->resolveCargo($team, $key);

            if ($role !== null) {
                $this->cargos()->attach($role->id, ['team_id' => $team->id]);
            }
        }

        $this->forgetCargoCache($team);
    }

    /**
     * Get the union of permission names granted by the user's cargos on the team.
     *
     * A super cargo short-circuits to the whole catalog.
     *
     * @return Collection<int, string>
     */
    public function permissionNamesForTeam(Team $team): Collection
    {
        if (isset($this->cargoPermissionCache[$team->id])) {
            return $this->cargoPermissionCache[$team->id];
        }

        $cargos = $this->cargos()
            ->wherePivot('team_id', $team->id)
            ->with('permissions')
            ->get();

        if ($cargos->contains(fn (Role $role): bool => $role->is_super)) {
            return $this->cargoPermissionCache[$team->id] = collect(PermissionCatalog::names());
        }

        return $this->cargoPermissionCache[$team->id] = $cargos
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * Determine if the user has the given permission name on the team.
     */
    public function hasPermissionForTeam(Team $team, string $name): bool
    {
        return $this->permissionNamesForTeam($team)->contains($name);
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->hasPermissionForTeam($team, $permission->value);
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $cargos = $this->cargosForTeam($team)
            ->map(fn (Role $role): array => ['key' => $role->key, 'name' => $role->name])
            ->values()
            ->all();

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            isOwner: $this->ownsTeam($team),
            cargos: $cargos,
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        return new TeamPermissions(
            canUpdateTeam: $this->hasPermissionForTeam($team, TeamPermission::UpdateTeam->value),
            canDeleteTeam: $this->hasPermissionForTeam($team, TeamPermission::DeleteTeam->value),
            canAddMember: $this->hasPermissionForTeam($team, TeamPermission::AddMember->value),
            canUpdateMember: $this->hasPermissionForTeam($team, TeamPermission::UpdateMember->value),
            canRemoveMember: $this->hasPermissionForTeam($team, TeamPermission::RemoveMember->value),
            canCreateInvitation: $this->hasPermissionForTeam($team, TeamPermission::CreateInvitation->value),
            canCancelInvitation: $this->hasPermissionForTeam($team, TeamPermission::CancelInvitation->value),
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Resolve a cargo by key, preferring a team-specific cargo over a global one.
     */
    protected function resolveCargo(Team $team, string $key): ?Role
    {
        return Role::query()
            ->byKey($key)
            ->where(function ($query) use ($team): void {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $team->id);
            })
            ->orderByRaw('team_id IS NULL')
            ->first();
    }

    /**
     * Forget the cached permission names for the given team.
     */
    protected function forgetCargoCache(Team $team): void
    {
        unset($this->cargoPermissionCache[$team->id]);
    }
}
