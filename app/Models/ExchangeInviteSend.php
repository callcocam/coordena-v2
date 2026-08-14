<?php

namespace App\Models;

use App\Enums\ExchangeInviteKind;
use App\Enums\ExchangeInviteSendStatus;
use Database\Factories\ExchangeInviteSendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Envio de um convite de troca a uma congregação parceira.
 *
 * @property string $id
 * @property string $invite_id
 * @property string $congregation_id
 * @property string $channel
 * @property ExchangeInviteKind $kind
 * @property string|null $portal_token
 * @property ExchangeInviteSendStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $nudged_at
 * @property string|null $sent_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExchangeInvite $invite
 * @property-read Congregation $congregation
 * @property-read User|null $sentBy
 * @property-read Collection<int, ExchangeMessage> $messages
 * @property-read Collection<int, ExchangeOffer> $offers
 */
#[Fillable([
    'invite_id',
    'congregation_id',
    'channel',
    'kind',
    'portal_token',
    'status',
    'sent_at',
    'answered_at',
    'accepted_at',
    'nudged_at',
    'sent_by_id',
])]
class ExchangeInviteSend extends Model
{
    /** @use HasFactory<ExchangeInviteSendFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExchangeInviteKind::class,
            'status' => ExchangeInviteSendStatus::class,
            'sent_at' => 'datetime',
            'answered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'nudged_at' => 'datetime',
        ];
    }

    /**
     * Get the invite this send belongs to.
     *
     * @return BelongsTo<ExchangeInvite, $this>
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(ExchangeInvite::class, 'invite_id');
    }

    /**
     * Get the partner congregation this send targets.
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
     * Get the conversation messages tied to this send.
     *
     * @return HasMany<ExchangeMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ExchangeMessage::class, 'invite_send_id');
    }

    /**
     * Get the speaker offers tied to this send.
     *
     * @return HasMany<ExchangeOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(ExchangeOffer::class, 'invite_send_id');
    }
}
