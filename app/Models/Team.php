<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Support\WhatsappTerms;
use Callcocam\WhatsAppCloud\Support\ArrayCredentials;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property bool $whatsapp_api_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'slug', 'is_personal', 'whatsapp_api_enabled', 'home_congregation_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, HasUlids, SoftDeletes;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'whatsapp_api_enabled' => true,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('is_owner', true)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['is_owner'])
            ->withTimestamps();
    }

    /**
     * Get the custom cargos owned by this team.
     *
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get the congregação do time (local dos discursos "home").
     *
     * @return BelongsTo<Congregation, $this>
     */
    public function homeCongregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class, 'home_congregation_id');
    }

    /**
     * Get the coordenadores de discursos deste time.
     *
     * @return HasMany<Coordinator, $this>
     */
    public function coordinators(): HasMany
    {
        return $this->hasMany(Coordinator::class);
    }

    /**
     * Get the team's WhatsApp Cloud API connection (Meta credentials).
     *
     * @return HasOne<TeamWhatsappConnection, $this>
     */
    public function whatsappConnection(): HasOne
    {
        return $this->hasOne(TeamWhatsappConnection::class);
    }

    /**
     * Whether the team wants to use the official WhatsApp Cloud API at all.
     */
    public function usesWhatsappApi(): bool
    {
        return (bool) $this->whatsapp_api_enabled;
    }

    /**
     * Whether the team's Meta Cloud credentials are present and usable.
     */
    public function isWhatsappConnected(): bool
    {
        return $this->whatsappConnection?->isConnected() ?? false;
    }

    /**
     * Whether someone on the team has accepted the current WhatsApp terms.
     */
    public function hasAcceptedWhatsappTerms(): bool
    {
        return WhatsappTerms::acceptedByTeam($this);
    }

    /**
     * Whether the team can actually send via the WhatsApp Cloud API right now:
     * the API is enabled, the terms are accepted, and either the team has its
     * own credentials or a shared default number is configured.
     */
    public function canSendWhatsappApi(): bool
    {
        if (! $this->usesWhatsappApi() || ! $this->hasAcceptedWhatsappTerms()) {
            return false;
        }

        return $this->isWhatsappConnected()
            || ArrayCredentials::fromArray(config('whatsapp-cloud.default')) !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'whatsapp_api_enabled' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
