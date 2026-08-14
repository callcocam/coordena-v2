<?php

namespace App\Models;

use App\Enums\ExchangeInviteStatus;
use Database\Factories\ExchangeInviteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Convite mensal de troca de oradores de um time.
 *
 * @property string $id
 * @property string $team_id
 * @property Carbon $month
 * @property ExchangeInviteStatus $status
 * @property string|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $createdBy
 * @property-read Collection<int, ExchangeInviteSend> $sends
 */
#[Fillable(['team_id', 'month', 'status', 'created_by_id'])]
class ExchangeInvite extends Model
{
    /** @use HasFactory<ExchangeInviteFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => ExchangeInviteStatus::class,
        ];
    }

    /**
     * Get the team this invite belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created this invite.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the sends of this invite to partner congregations.
     *
     * @return HasMany<ExchangeInviteSend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(ExchangeInviteSend::class, 'invite_id');
    }
}
