<?php

namespace App\Models;

use App\Enums\ExchangeOfferStatus;
use Database\Factories\ExchangeOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Oferta de orador (entrada ou saída) dentro de um envio de convite.
 *
 * @property string $id
 * @property string $invite_send_id
 * @property string $direction
 * @property string $speaker_id
 * @property Carbon|null $target_date
 * @property string|null $chosen_outline_id
 * @property ExchangeOfferStatus $status
 * @property string|null $source_message_id
 * @property string|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExchangeInviteSend $inviteSend
 * @property-read Speaker $speaker
 * @property-read ExchangeMessage|null $sourceMessage
 * @property-read User|null $createdBy
 * @property-read PublicTalkOutline|null $chosenOutline
 * @property-read Collection<int, PublicTalkOutline> $outlines
 */
#[Fillable([
    'invite_send_id',
    'direction',
    'speaker_id',
    'target_date',
    'chosen_outline_id',
    'status',
    'source_message_id',
    'created_by_id',
])]
class ExchangeOffer extends Model
{
    /** @use HasFactory<ExchangeOfferFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'status' => ExchangeOfferStatus::class,
        ];
    }

    /**
     * Get the invite send this offer belongs to.
     *
     * @return BelongsTo<ExchangeInviteSend, $this>
     */
    public function inviteSend(): BelongsTo
    {
        return $this->belongsTo(ExchangeInviteSend::class, 'invite_send_id');
    }

    /**
     * Get the offered speaker.
     *
     * @return BelongsTo<Speaker, $this>
     */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    /**
     * Get the inbound message that originated this offer, when any.
     *
     * @return BelongsTo<ExchangeMessage, $this>
     */
    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(ExchangeMessage::class, 'source_message_id');
    }

    /**
     * Get the user who registered this offer.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the outline chosen by our coordinator for this offer, when any.
     *
     * @return BelongsTo<PublicTalkOutline, $this>
     */
    public function chosenOutline(): BelongsTo
    {
        return $this->belongsTo(PublicTalkOutline::class, 'chosen_outline_id');
    }

    /**
     * Get the outlines proposed for this offer.
     *
     * @return BelongsToMany<PublicTalkOutline, $this>
     */
    public function outlines(): BelongsToMany
    {
        return $this->belongsToMany(PublicTalkOutline::class, 'exchange_offer_outlines', 'offer_id', 'outline_id');
    }
}
