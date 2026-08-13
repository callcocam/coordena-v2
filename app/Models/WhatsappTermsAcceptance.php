<?php

namespace App\Models;

use Database\Factories\WhatsappTermsAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit record proving a user accepted the WhatsApp connection terms for a
 * team: who, when, from which IP / user agent, and which terms version.
 *
 * @property string $id
 * @property string $team_id
 * @property string $user_id
 * @property string $version
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'version', 'ip_address', 'user_agent', 'accepted_at'])]
class WhatsappTermsAcceptance extends Model
{
    /** @use HasFactory<WhatsappTermsAcceptanceFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }
}
