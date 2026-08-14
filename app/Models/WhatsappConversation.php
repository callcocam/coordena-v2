<?php

namespace App\Models;

use Database\Factories\WhatsappConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Conversa guiada de WhatsApp entre um coordenador e o bot do time.
 *
 * Uma linha por time+telefone; a janela expira em 24h sem mensagens e o
 * estado volta ao menu na próxima interação.
 *
 * @property string $id
 * @property string $team_id
 * @property string $phone
 * @property string $coordinator_id
 * @property string $state
 * @property array<string, mixed>|null $context
 * @property Carbon|null $last_message_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Coordinator $coordinator
 */
#[Fillable(['team_id', 'phone', 'coordinator_id', 'state', 'context', 'last_message_at', 'expires_at'])]
class WhatsappConversation extends Model
{
    /** @use HasFactory<WhatsappConversationFactory> */
    use HasFactory, HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_message_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the team this conversation belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the coordinator on the other side of the conversation.
     *
     * @return BelongsTo<Coordinator, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Coordinator::class);
    }

    /**
     * Whether the 24h window of this conversation is already gone.
     */
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    /**
     * Read a value from the conversation context.
     */
    public function contextValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->context ?? [], $key, $default);
    }

    /**
     * Merge values into the conversation context (without saving).
     */
    public function mergeContext(array $values): static
    {
        $this->context = array_merge($this->context ?? [], $values);

        return $this;
    }
}
