<?php

namespace App\Models;

use App\Enums\CongregationIntroStatus;
use Database\Factories\CongregationIntroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Apresentação (primeiro contato) de um time a uma congregação parceira —
 * o registro auditável de "já nos apresentamos?".
 *
 * @property string $id
 * @property string $team_id
 * @property string $congregation_id
 * @property string $channel
 * @property string|null $portal_token
 * @property CongregationIntroStatus $status
 * @property string|null $wamid
 * @property string|null $reactivation_wamid
 * @property Carbon|null $reactivation_prompted_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $reactivated_at
 * @property string|null $sent_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Congregation $congregation
 * @property-read User|null $sentBy
 * @property-read Collection<int, CongregationIntroMessage> $messages
 */
#[Fillable([
    'team_id',
    'congregation_id',
    'channel',
    'portal_token',
    'status',
    'wamid',
    'reactivation_wamid',
    'reactivation_prompted_at',
    'sent_at',
    'responded_at',
    'declined_at',
    'reactivated_at',
    'sent_by_id',
])]
class CongregationIntro extends Model
{
    /** @use HasFactory<CongregationIntroFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CongregationIntroStatus::class,
            'reactivation_prompted_at' => 'datetime',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'declined_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that sent this intro.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the congregation this intro was sent to.
     *
     * @return BelongsTo<Congregation, $this>
     */
    public function congregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class);
    }

    /**
     * Get the user who triggered the send.
     *
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    /**
     * Get the full message history of this intro.
     *
     * @return HasMany<CongregationIntroMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CongregationIntroMessage::class, 'intro_id');
    }

    /**
     * Scope to the intros of a team/congregation pair.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForPair(Builder $query, Team|string $team, Congregation|string $congregation): void
    {
        $query
            ->where('team_id', $team instanceof Team ? $team->id : $team)
            ->where('congregation_id', $congregation instanceof Congregation ? $congregation->id : $congregation);
    }
}
