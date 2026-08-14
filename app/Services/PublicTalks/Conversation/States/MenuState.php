<?php

namespace App\Services\PublicTalks\Conversation\States;

use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\ConversationState;
use App\Services\PublicTalks\Conversation\Prompt;
use Illuminate\Support\Arr;

/**
 * Menu inicial da conversa: cumprimenta o coordenador pelo nome (com saudação
 * variada, para não soar robótico) e oferece as áreas disponíveis.
 */
class MenuState implements ConversationState
{
    public function prompt(WhatsappConversation $conversation): Prompt
    {
        $greeting = Arr::random(__('app.public_talks.conversation.menu.greetings'));

        return new Prompt(
            __('app.public_talks.conversation.menu.body', [
                'greeting' => $greeting,
                'name' => $conversation->coordinator->name,
            ]),
            [
                'week_view' => __('app.public_talks.conversation.menu.options.week_view'),
                'exchange_view' => __('app.public_talks.conversation.menu.options.exchange_view'),
            ],
        );
    }

    public function apply(WhatsappConversation $conversation, string $option): ?string
    {
        return match ($option) {
            'week_view' => $this->openWeekView($conversation),
            'exchange_view' => 'exchange_view',
            default => null,
        };
    }

    /**
     * Enter the week view always starting at the upcoming weekend.
     */
    protected function openWeekView(WhatsappConversation $conversation): string
    {
        $conversation->mergeContext(['week_offset' => 0]);

        return 'week_view';
    }
}
