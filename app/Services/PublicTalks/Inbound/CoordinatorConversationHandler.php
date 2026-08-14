<?php

namespace App\Services\PublicTalks\Inbound;

use App\Models\Coordinator;
use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\ConversationEngine;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

/**
 * Handler 2 — conversa aberta do coordenador (menu guiado pelo WhatsApp).
 *
 * Casa quando o remetente é um coordenador ativo de algum time. Fica ANTES
 * dos fluxos de parceira e orador de propósito: o coordenador pode ser
 * contato de congregação e orador ao mesmo tempo, e a conversa dele com o
 * bot tem precedência. A mensagem é entregue à {@see ConversationEngine},
 * que decide entre abrir conversa nova (menu) ou avançar a existente.
 */
class CoordinatorConversationHandler implements InboundHandler
{
    public function __construct(
        protected ConversationEngine $engine,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        return $this->coordinatorFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $coordinator = $this->coordinatorFor($message);

        if ($coordinator === null) {
            $message->markUnhandled();

            return;
        }

        $conversation = WhatsappConversation::query()->firstOrNew([
            'team_id' => $coordinator->team_id,
            'phone' => Phone::normalize($message->wa_id),
        ], [
            'coordinator_id' => $coordinator->id,
            'state' => 'menu',
            'context' => [],
        ]);

        $this->engine->handle($conversation, $message);

        // O bot respondeu na própria conversa; registra o destino da resposta.
        $message->markForwarded($conversation->phone);
    }

    /**
     * The active coordinator whose phone is the sender, when any.
     */
    protected function coordinatorFor(WhatsAppInboundMessage $message): ?Coordinator
    {
        $phone = Phone::normalize($message->wa_id);

        if ($phone === null) {
            return null;
        }

        return Coordinator::query()
            ->active()
            ->where('phone', $phone)
            ->orderBy('created_at')
            ->first();
    }
}
