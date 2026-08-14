<?php

namespace App\Services\PublicTalks\Conversation;

use App\Models\WhatsappConversation;

/**
 * Um estado da conversa guiada do coordenador.
 *
 * O estado é declarativo: `prompt()` monta o que mostrar ao entrar (a engine
 * envia e grava as opções no contexto) e `apply()` reage à opção escolhida,
 * devolvendo o nome do próximo estado — ou null quando a opção não muda nada
 * e o prompt atual deve ser reapresentado.
 */
interface ConversationState
{
    /**
     * The prompt shown when the conversation enters this state.
     */
    public function prompt(WhatsappConversation $conversation): Prompt;

    /**
     * Apply the chosen option; returns the next state name (may be the same
     * state, to re-enter with a changed context) or null to re-present.
     */
    public function apply(WhatsappConversation $conversation, string $option): ?string;
}
