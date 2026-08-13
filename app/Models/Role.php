<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $team_id
 * @property string $key
 * @property string $name
 * @property bool $is_default
 * @property bool $is_super
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $team
 * @property-read Collection<int, Permission> $permissions
 */
#[Fillable(['team_id', 'key', 'name', 'is_default', 'is_super'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_super' => false,
    ];

    /**
     * Get the team that owns this custom role, if any.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the permissions granted by this role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Scope to global catalog roles (not owned by any team).
     *
     * @param  Builder<Role>  $query
     */
    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('team_id');
    }

    /**
     * Scope to the base role assigned on joining a team.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    /**
     * Scope to a role by its stable key.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeByKey(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }

    /**
     * Scope to the cargos assignable to members of the given team: global
     * catalog cargos and the team's own custom cargos, excluding super cargos.
     *
     * @param  Builder<Role>  $query
     */
    public function scopeAssignableForTeam(Builder $query, Team $team): void
    {
        $query->where('is_super', false)
            ->where(function (Builder $query) use ($team): void {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $team->id);
            });
    }

    /**
     * Whether this role grants every permission (super role).
     */
    public function grantsAll(): bool
    {
        return $this->is_super;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_super' => 'boolean',
        ];
    }
}
