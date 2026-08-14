<?php

namespace App\Services\PublicTalks\Conversation;

use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\States\ConfirmDispatchState;
use App\Services\PublicTalks\Conversation\States\ExchangeViewState;
use App\Services\PublicTalks\Conversation\States\MenuState;
use App\Services\PublicTalks\Conversation\States\WeekViewState;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

/**
 * Máquina de estados da conversa guiada do coordenador no WhatsApp.
 *
 * O fluxo inteiro é: mensagem entra → resolve a opção escolhida (linha da
 * lista interativa, número ou rótulo digitado) → o estado atual aplica a
 * opção e diz o próximo estado → a engine envia o prompt do novo estado e
 * grava as opções no contexto para interpretar a resposta seguinte.
 *
 * Janela de 24h: sem mensagem nesse período a conversa expira e a próxima
 * mensagem volta ao menu, sem interpretar o texto (um "oi" três dias depois
 * não pode disparar a opção 2 de um prompt esquecido).
 */
class ConversationEngine
{
    /**
     * Nome do estado => classe. Todo estado novo entra aqui.
     *
     * @var array<string, class-string<ConversationState>>
     */
    public const STATES = [
        'menu' => MenuState::class,
        'week_view' => WeekViewState::class,
        'confirm_dispatch' => ConfirmDispatchState::class,
        'exchange_view' => ExchangeViewState::class,
    ];

    /**
     * How long a conversation window stays open after the last message.
     */
    public const WINDOW_HOURS = 24;

    public function __construct(
        protected Container $container,
        protected ConversationMessenger $messenger,
    ) {}

    /**
     * Advance the conversation with an incoming coordinator message.
     */
    public function handle(WhatsappConversation $conversation, WhatsAppInboundMessage $message): void
    {
        $fresh = ! $conversation->exists || $conversation->isExpired();

        $conversation->forceFill([
            'last_message_at' => now(),
            'expires_at' => now()->addHours(self::WINDOW_HOURS),
        ]);

        if ($fresh) {
            $conversation->forceFill(['state' => 'menu', 'context' => []]);
            $this->transition($conversation, 'menu');

            return;
        }

        $option = $this->selectedOption($conversation, $message);

        if ($option === null) {
            $this->represent($conversation, withApology: true);

            return;
        }

        $next = $this->stateFor($conversation->state)->apply($conversation, $option);

        $next === null
            ? $this->represent($conversation, withApology: false)
            : $this->transition($conversation, $next);
    }

    /**
     * Enter `$state`: send its prompt and remember the offered options.
     */
    protected function transition(WhatsappConversation $conversation, string $state): void
    {
        $conversation->state = $state;

        $prompt = $this->stateFor($state)->prompt($conversation);

        $conversation->mergeContext(['options' => $prompt->options])->save();

        $this->messenger->sendPrompt($conversation, $prompt);
    }

    /**
     * Re-send the current state prompt (optionally with an "I did not get
     * that" line) without changing state.
     */
    protected function represent(WhatsappConversation $conversation, bool $withApology): void
    {
        $prompt = $this->stateFor($conversation->state)->prompt($conversation);

        $conversation->mergeContext(['options' => $prompt->options])->save();

        $body = $withApology
            ? __('app.public_talks.conversation.unknown_reply')."\n\n".$prompt->body
            : $prompt->body;

        $this->messenger->sendPrompt($conversation, new Prompt($body, $prompt->options));
    }

    /**
     * The option key the coordinator picked, matching the interactive list
     * row title, the typed number ("2") or the typed label — or null when
     * nothing matches the options offered by the current prompt.
     */
    protected function selectedOption(WhatsappConversation $conversation, WhatsAppInboundMessage $message): ?string
    {
        /** @var array<string, string> $options */
        $options = $conversation->contextValue('options', []);

        $input = trim($this->inputText($message) ?? '');

        if ($options === [] || $input === '') {
            return null;
        }

        $keys = array_keys($options);

        if (ctype_digit($input)) {
            $index = (int) $input - 1;

            return $keys[$index] ?? null;
        }

        $normalized = mb_strtolower($input);

        foreach ($options as $key => $label) {
            // Meta corta o título da linha em 24 chars, então a resposta da
            // lista interativa pode chegar como prefixo do rótulo completo.
            $title = mb_strtolower(mb_substr($label, 0, 24));

            if ($normalized === mb_strtolower($label) || $normalized === $title) {
                return $key;
            }

            if (mb_strlen($normalized) >= 3 && Str::startsWith($title, $normalized)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The reply as text: interactive list/button title or the plain body.
     */
    protected function inputText(WhatsAppInboundMessage $message): ?string
    {
        $payload = $message->payload ?? [];

        $text = $payload['interactive']['list_reply']['title']
            ?? $payload['interactive']['button_reply']['title']
            ?? $payload['button']['text']
            ?? $message->text;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * Resolve a state handler by its name (unknown names fall back to menu).
     */
    protected function stateFor(string $state): ConversationState
    {
        $class = self::STATES[$state] ?? MenuState::class;

        /** @var ConversationState */
        return $this->container->make($class);
    }
}
