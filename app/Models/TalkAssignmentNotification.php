<?php

namespace App\Models;

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use Database\Factories\TalkAssignmentNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notificação (designação ou lembrete) enviada ao orador de um discurso.
 *
 * @property string $id
 * @property string $talk_assignment_id
 * @property string $speaker_id
 * @property SpeakerNotificationKind $kind
 * @property string|null $wamid
 * @property SpeakerNotificationStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property array<string, mixed>|null $response_payload
 * @property string|null $sent_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TalkAssignment $assignment
 * @property-read Speaker $speaker
 * @property-read User|null $sentBy
 */
#[Fillable([
    'talk_assignment_id',
    'speaker_id',
    'kind',
    'wamid',
    'status',
    'sent_at',
    'responded_at',
    'response_payload',
    'sent_by_id',
])]
class TalkAssignmentNotification extends Model
{
    /** @use HasFactory<TalkAssignmentNotificationFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SpeakerNotificationKind::class,
            'status' => SpeakerNotificationStatus::class,
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }

    /**
     * Get the assignment this notification belongs to.
     *
     * @return BelongsTo<TalkAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TalkAssignment::class, 'talk_assignment_id');
    }

    /**
     * Get the notified speaker.
     *
     * @return BelongsTo<Speaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
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
}
