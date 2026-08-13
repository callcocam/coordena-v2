<?php

namespace App\Models;

use Callcocam\WhatsAppCloud\Contracts\WhatsAppCredentials;
use Callcocam\WhatsAppCloud\Support\HasWhatsAppCredentials;
use Database\Factories\TeamWhatsappConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $team_id
 * @property string|null $waba_id
 * @property string|null $phone_number_id
 * @property string|null $cloud_access_token
 * @property string|null $app_id
 * @property string|null $verified_name
 * @property string|null $quality_rating
 * @property string|null $messaging_limit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['waba_id', 'phone_number_id', 'cloud_access_token', 'app_id', 'verified_name', 'quality_rating', 'messaging_limit'])]
#[Hidden(['cloud_access_token'])]
class TeamWhatsappConnection extends Model implements WhatsAppCredentials
{
    /** @use HasFactory<TeamWhatsappConnectionFactory> */
    use HasFactory;

    use HasUlids;

    // Implements WhatsAppCredentials from the package (phoneNumberId/accessToken/
    // wabaId/graphVersion) by reading this model's columns. hasCloudCredentials()
    // below defines the project's own "is it usable?" rule.
    use HasWhatsAppCredentials;

    /**
     * Get the team that owns this connection.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Whether this connection can send — i.e. the Meta Cloud credentials are
     * present. There is no separate connect/QR step, so "connected",
     * "provisioned" and "has credentials" are the same thing.
     */
    public function isConnected(): bool
    {
        return $this->hasCloudCredentials();
    }

    /**
     * Alias of {@see isConnected()} — kept for the call sites that ask whether
     * the number is set up.
     */
    public function isProvisioned(): bool
    {
        return $this->hasCloudCredentials();
    }

    /**
     * Whether the Meta Cloud API credentials are present and usable — the team
     * can send official messages once these are filled in.
     */
    public function hasCloudCredentials(): bool
    {
        return filled($this->phone_number_id) && filled($this->cloud_access_token);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cloud_access_token' => 'encrypted',
        ];
    }
}
