<?php

namespace App\Services\PublicTalks\Conversation;

use App\Models\WhatsappConversation;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\InteractiveMessage;
use Illuminate\Support\Facades\Log;

/**
 * Envia as mensagens da conversa guiada. Prompts com opções saem como lista
 * interativa da Meta; se a API recusar (aparelho/numero sem suporte), cai para
 * texto de sessão com as opções numeradas — a engine também entende resposta
 * por número. Falha total vira warning no log e não estoura o webhook.
 */
class ConversationMessenger
{
    /**
     * Send a state prompt to the coordinator.
     */
    public function sendPrompt(WhatsappConversation $conversation, Prompt $prompt): void
    {
        if ($prompt->options === []) {
            $this->sendText($conversation, $prompt->body);

            return;
        }

        try {
            WhatsApp::for($conversation->team)->sendInteractive(
                $conversation->phone,
                new InteractiveMessage($prompt->body, array_values($prompt->options)),
            );
        } catch (CloudApiException) {
            $this->sendText($conversation, $this->numberedFallback($prompt));
        }
    }

    /**
     * Send a plain session text inside the open 24h window.
     */
    public function sendText(WhatsappConversation $conversation, string $body): void
    {
        try {
            WhatsApp::for($conversation->team)->sendSessionText($conversation->phone, $body);
        } catch (CloudApiException $exception) {
            Log::warning('Coordinator conversation message not delivered.', [
                'team_id' => $conversation->team_id,
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The prompt as plain text with numbered options ("1. …").
     */
    protected function numberedFallback(Prompt $prompt): string
    {
        $lines = [];

        foreach (array_values($prompt->options) as $index => $label) {
            $lines[] = ($index + 1).'. '.$label;
        }

        return $prompt->body."\n\n".implode("\n", $lines);
    }
}
