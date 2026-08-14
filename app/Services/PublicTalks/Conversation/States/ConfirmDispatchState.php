<?php

namespace App\Services\PublicTalks\Conversation\States;

use App\Enums\SpeakerNotificationKind;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\TalkAssignment;
use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\ConversationMessenger;
use App\Services\PublicTalks\Conversation\ConversationState;
use App\Services\PublicTalks\Conversation\Prompt;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * Confirmação explícita antes de disparar as notificações pendentes da
 * semana em foco. Os ids vêm do contexto (gravados pelo WeekViewState), mas
 * cada discurso é revalidado na hora do "sim" — entre o resumo e a resposta
 * alguém pode ter notificado pela mesa ou trocado o orador.
 */
class ConfirmDispatchState implements ConversationState
{
    public function __construct(
        protected ConversationMessenger $messenger,
    ) {}

    public function prompt(WhatsappConversation $conversation): Prompt
    {
        $count = count((array) $conversation->contextValue('pending_ids', []));

        return new Prompt(
            trans_choice('app.public_talks.conversation.dispatch.confirm', $count, ['count' => $count]),
            [
                'yes' => __('app.public_talks.conversation.options.yes'),
                'no' => __('app.public_talks.conversation.options.no'),
            ],
        );
    }

    public function apply(WhatsappConversation $conversation, string $option): ?string
    {
        return match ($option) {
            'yes' => $this->dispatch($conversation),
            'no' => 'menu',
            default => null,
        };
    }

    /**
     * Queue one assignment notification per still-eligible pending talk and
     * tell the coordinator how many went out.
     */
    protected function dispatch(WhatsappConversation $conversation): string
    {
        $assignments = $this->eligibleAssignments($conversation);

        foreach ($assignments as $assignment) {
            SendSpeakerAssignmentNotification::queueFor($assignment, SpeakerNotificationKind::Assignment);
        }

        $summary = $assignments->isEmpty()
            ? __('app.public_talks.conversation.dispatch.none')
            : trans_choice('app.public_talks.conversation.dispatch.done', $assignments->count(), ['count' => $assignments->count()]);

        $this->messenger->sendText($conversation, $summary);

        $conversation->mergeContext(['pending_ids' => []]);

        return 'menu';
    }

    /**
     * Reload the pending ids and keep only what is still notifiable now.
     *
     * @return Collection<int, TalkAssignment>
     */
    protected function eligibleAssignments(WhatsappConversation $conversation): Collection
    {
        $ids = (array) $conversation->contextValue('pending_ids', []);

        if ($ids === []) {
            return new Collection;
        }

        return TalkAssignment::query()
            ->with('speaker')
            ->where('team_id', $conversation->team_id)
            ->whereIn('id', $ids)
            ->where('type', TalkAssignmentType::Home)
            ->where('status', TalkAssignmentStatus::Scheduled)
            ->whereNotNull('speaker_id')
            ->whereNotNull('outline_id')
            ->get()
            ->filter(fn (TalkAssignment $assignment): bool => Phone::normalize($assignment->speaker?->phone) !== null)
            ->values();
    }
}
