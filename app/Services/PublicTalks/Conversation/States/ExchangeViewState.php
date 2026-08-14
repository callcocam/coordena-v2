<?php

namespace App\Services\PublicTalks\Conversation\States;

use App\Enums\ExchangeInviteStatus;
use App\Models\ExchangeInvite;
use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\ConversationState;
use App\Services\PublicTalks\Conversation\Prompt;
use Illuminate\Support\Collection;

/**
 * Situação das rodadas de troca em aberto: para cada convite ativo,
 * quantas congregações responderam e quantas seguem pendentes. Estado
 * somente-leitura — as ações de troca continuam no portal/mesa.
 */
class ExchangeViewState implements ConversationState
{
    public function prompt(WhatsappConversation $conversation): Prompt
    {
        return new Prompt($this->body($this->openInvites($conversation)), [
            'menu' => __('app.public_talks.conversation.options.menu'),
        ]);
    }

    public function apply(WhatsappConversation $conversation, string $option): ?string
    {
        return $option === 'menu' ? 'menu' : null;
    }

    /**
     * The still-active invites of the team, most recent month first.
     *
     * @return Collection<int, ExchangeInvite>
     */
    protected function openInvites(WhatsappConversation $conversation): Collection
    {
        return ExchangeInvite::query()
            ->with('sends.congregation')
            ->where('team_id', $conversation->team_id)
            ->whereIn('status', [ExchangeInviteStatus::Open, ExchangeInviteStatus::PartiallyFilled])
            ->orderByDesc('month')
            ->get();
    }

    /**
     * The exchange summary text.
     *
     * @param  Collection<int, ExchangeInvite>  $invites
     */
    protected function body(Collection $invites): string
    {
        if ($invites->isEmpty()) {
            return __('app.public_talks.conversation.exchange.empty');
        }

        $lines = [__('app.public_talks.conversation.exchange.header')];

        foreach ($invites as $invite) {
            $answered = $invite->sends->whereNotNull('answered_at')->count();

            $lines[] = __('app.public_talks.conversation.exchange.line', [
                'month' => $invite->month->translatedFormat('F/Y'),
                'answered' => $answered,
                'total' => $invite->sends->count(),
                'status' => __('app.public_talks.exchange.statuses.'.$invite->status->value),
            ]);
        }

        return implode("\n", $lines);
    }
}
