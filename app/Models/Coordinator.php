<?php

namespace App\Models;

use App\Enums\CoordinatorRole;
use App\Support\Phone;
use Database\Factories\CoordinatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Coordenador de discursos (responsável ou ajudante) de um time.
 *
 * @property string $id
 * @property string $team_id
 * @property string $name
 * @property string|null $phone
 * @property CoordinatorRole $role
 * @property bool $is_active
 * @property string|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'name', 'phone', 'role', 'is_active', 'user_id'])]
class Coordinator extends Model
{
    /** @use HasFactory<CoordinatorFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CoordinatorRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the team this coordinator belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the linked user account, when any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to active coordinators.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Normalize the phone before persisting.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => Phone::normalize($value));
    }
}
