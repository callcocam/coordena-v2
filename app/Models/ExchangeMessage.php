<?php

namespace App\Models;

use Database\Factories\ExchangeMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mensagem trocada (inbound/outbound) dentro de um envio de convite.
 *
 * @property string $id
 * @property string $invite_send_id
 * @property string $direction
 * @property string $channel
 * @property string $body
 * @property string|null $wamid
 * @property Carbon|null $created_at
 * @property-read ExchangeInviteSend $inviteSend
 */
#[Fillable(['invite_send_id', 'direction', 'channel', 'body', 'wamid'])]
class ExchangeMessage extends Model
{
    /** @use HasFactory<ExchangeMessageFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /**
     * Get the invite send this message belongs to.
     *
     * @return BelongsTo<ExchangeInviteSend, $this>
     */
    public function inviteSend(): BelongsTo
    {
        return $this->belongsTo(ExchangeInviteSend::class, 'invite_send_id');
    }
}
