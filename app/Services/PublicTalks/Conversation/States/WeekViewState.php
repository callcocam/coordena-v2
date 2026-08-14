<?php

namespace App\Services\PublicTalks\Conversation\States;

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Conversation\ConversationState;
use App\Services\PublicTalks\Conversation\Prompt;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resumo do fim de semana (atual ou próximo, via `week_offset` no contexto):
 * quem discursa, esboço e situação de cada linha da programação. Quando há
 * orador programado ainda sem notificação, oferece o disparo — os ids ficam
 * no contexto e a confirmação acontece no {@see ConfirmDispatchState}.
 */
class WeekViewState implements ConversationState
{
    public function prompt(WhatsappConversation $conversation): Prompt
    {
        $date = $this->weekendDate($conversation);
        $assignments = $this->assignmentsFor($conversation, $date);
        $pending = $this->pendingNotifications($assignments);

        $conversation->mergeContext([
            'pending_ids' => $pending->pluck('id')->all(),
        ]);

        $offset = (int) $conversation->contextValue('week_offset', 0);

        $options = [];

        if ($pending->isNotEmpty()) {
            $options['dispatch'] = trans_choice('app.public_talks.conversation.week.options.dispatch', $pending->count(), ['count' => $pending->count()]);
        }

        $options[$offset === 0 ? 'next_week' : 'current_week'] = $offset === 0
            ? __('app.public_talks.conversation.week.options.next_week')
            : __('app.public_talks.conversation.week.options.current_week');

        $options['menu'] = __('app.public_talks.conversation.options.menu');

        return new Prompt($this->body($date, $assignments, $pending), $options);
    }

    public function apply(WhatsappConversation $conversation, string $option): ?string
    {
        return match ($option) {
            'next_week' => $this->shiftWeek($conversation, 1),
            'current_week' => $this->shiftWeek($conversation, 0),
            'dispatch' => 'confirm_dispatch',
            'menu' => 'menu',
            default => null,
        };
    }

    /**
     * Move the view to another weekend and re-enter the state.
     */
    protected function shiftWeek(WhatsappConversation $conversation, int $offset): string
    {
        $conversation->mergeContext(['week_offset' => $offset]);

        return 'week_view';
    }

    /**
     * The weekend date being shown: the upcoming meeting weekday of the
     * team, plus the week offset kept in the conversation context.
     */
    protected function weekendDate(WhatsappConversation $conversation): Carbon
    {
        $weekday = $conversation->team->homeCongregation?->meeting_weekday ?? Carbon::SATURDAY;

        $date = Carbon::today();

        if ($date->dayOfWeek !== $weekday) {
            $date = $date->next($weekday);
        }

        return $date->addWeeks((int) $conversation->contextValue('week_offset', 0))->startOfDay();
    }

    /**
     * The home/incoming talks of that weekend.
     *
     * @return Collection<int, TalkAssignment>
     */
    protected function assignmentsFor(WhatsappConversation $conversation, Carbon $date): Collection
    {
        return TalkAssignment::query()
            ->with(['speaker', 'outline', 'counterpartCongregation'])
            ->where('team_id', $conversation->team_id)
            ->whereDate('date', $date)
            ->whereIn('type', [TalkAssignmentType::Home, TalkAssignmentType::Incoming])
            ->orderBy('date')
            ->get();
    }

    /**
     * The talks scheduled but not yet notified, with a speaker reachable on
     * WhatsApp — the ones the coordinator can fire right from the chat.
     *
     * @param  Collection<int, TalkAssignment>  $assignments
     * @return Collection<int, TalkAssignment>
     */
    protected function pendingNotifications(Collection $assignments): Collection
    {
        return $assignments
            ->filter(fn (TalkAssignment $assignment): bool => $assignment->type === TalkAssignmentType::Home
                && $assignment->status === TalkAssignmentStatus::Scheduled
                && $assignment->speaker_id !== null
                && $assignment->outline_id !== null
                && Phone::normalize($assignment->speaker?->phone) !== null)
            ->values();
    }

    /**
     * The weekend summary text.
     *
     * @param  Collection<int, TalkAssignment>  $assignments
     * @param  Collection<int, TalkAssignment>  $pending
     */
    protected function body(Carbon $date, Collection $assignments, Collection $pending): string
    {
        $lines = [__('app.public_talks.conversation.week.header', [
            'date' => $date->translatedFormat('d/m (l)'),
        ])];

        if ($assignments->isEmpty()) {
            $lines[] = __('app.public_talks.conversation.week.empty');
        }

        foreach ($assignments as $assignment) {
            $lines[] = $this->assignmentLine($assignment);
        }

        if ($pending->isNotEmpty()) {
            $lines[] = '';
            $lines[] = trans_choice('app.public_talks.conversation.week.pending', $pending->count(), ['count' => $pending->count()]);
        }

        return implode("\n", $lines);
    }

    /**
     * One line of the summary: speaker, outline and status of a talk.
     */
    protected function assignmentLine(TalkAssignment $assignment): string
    {
        $speaker = $assignment->speaker?->name
            ?? $assignment->counterpartCongregation?->name
            ?? __('app.public_talks.schedule.no_speaker');

        $outline = $assignment->outline !== null
            ? __('app.public_talks.conversation.week.outline', ['number' => $assignment->outline->number])
            : '';

        $status = __('app.public_talks.schedule.statuses.'.$assignment->status->value);

        return __('app.public_talks.conversation.week.line', [
            'speaker' => $speaker,
            'outline' => $outline,
            'status' => $status,
        ]);
    }
}
