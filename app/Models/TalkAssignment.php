<?php

namespace App\Models;

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use Database\Factories\TalkAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Discurso de um fim de semana na programação do time.
 *
 * @property string $id
 * @property string $team_id
 * @property Carbon $date
 * @property TalkAssignmentType $type
 * @property string|null $speaker_id
 * @property string|null $outline_id
 * @property string|null $counterpart_congregation_id
 * @property TalkAssignmentStatus $status
 * @property string|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Speaker|null $speaker
 * @property-read PublicTalkOutline|null $outline
 * @property-read Congregation|null $counterpartCongregation
 * @property-read User|null $createdBy
 * @property-read Collection<int, TalkAssignmentNotification> $notifications
 */
#[Fillable([
    'team_id',
    'date',
    'type',
    'speaker_id',
    'outline_id',
    'counterpart_congregation_id',
    'status',
    'created_by_id',
])]
class TalkAssignment extends Model
{
    /** @use HasFactory<TalkAssignmentFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TalkAssignmentType::class,
            'status' => TalkAssignmentStatus::class,
        ];
    }

    /**
     * Get the team this assignment belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the assigned speaker, when any.
     *
     * @return BelongsTo<Speaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    /**
     * Get the assigned outline, when any.
     *
     * @return BelongsTo<PublicTalkOutline, $this>
     */
    public function outline(): BelongsTo
    {
        return $this->belongsTo(PublicTalkOutline::class, 'outline_id');
    }

    /**
     * Get the counterpart congregation (destino ou origem da permuta).
     *
     * @return BelongsTo<Congregation, $this>
     */
    public function counterpartCongregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class, 'counterpart_congregation_id');
    }

    /**
     * Get the user who created this assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the notifications sent for this assignment.
     *
     * @return HasMany<TalkAssignmentNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TalkAssignmentNotification::class);
    }
}
