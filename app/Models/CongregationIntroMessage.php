<?php

namespace App\Models;

use Database\Factories\CongregationIntroMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mensagem trocada (inbound/outbound) dentro de uma apresentação.
 *
 * @property string $id
 * @property string $intro_id
 * @property string $direction
 * @property string $channel
 * @property string $body
 * @property string|null $wamid
 * @property Carbon|null $created_at
 * @property-read CongregationIntro $intro
 */
#[Fillable(['intro_id', 'direction', 'channel', 'body', 'wamid'])]
class CongregationIntroMessage extends Model
{
    /** @use HasFactory<CongregationIntroMessageFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /**
     * Get the intro this message belongs to.
     *
     * @return BelongsTo<CongregationIntro, $this>
     */
    public function intro(): BelongsTo
    {
        return $this->belongsTo(CongregationIntro::class, 'intro_id');
    }
}
