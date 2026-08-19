<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\SpeakerNotificationKind;
use App\Enums\TalkAssignmentType;
use App\Http\Controllers\Controller;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\TalkAssignment;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Throwable;

/**
 * As ações de notificação WhatsApp da programação (designação/lembrete ao
 * orador do slot e o disparo agrupado da semana de troca), extraídas do
 * ScheduleController para ele ficar só com a tela.
 */
class ScheduleNotificationController extends Controller
{
    /**
     * Send the WhatsApp notification (assignment or reminder) to the slot's
     * speaker. All directions are allowed — the template copy varies by type
     * (local, quem sai, orador visitante), resolved per assignment by
     * TalkAssignmentMessage. Re-sending is allowed: each call creates a fresh
     * notification row.
     */
    public function notify(Request $request, string $current_team, TalkAssignment $assignment): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($assignment->team_id === $team->id, 404);

        Gate::authorize('notify', $assignment);

        $kind = SpeakerNotificationKind::tryFrom((string) $request->input('kind', SpeakerNotificationKind::Assignment->value));

        if ($kind === null) {
            return $this->notifyError(__('Tipo de notificação inválido.'));
        }

        if ($assignment->speaker_id === null || $assignment->outline_id === null) {
            return $this->notifyError(__('Preencha orador e esboço antes de notificar.'));
        }

        if (Phone::normalize($assignment->speaker?->phone) === null) {
            return $this->notifyError(__('O orador não tem um telefone válido para WhatsApp.'));
        }

        if (! $team->canSendWhatsappApi()) {
            return $this->notifyError(__('O WhatsApp do time não está pronto para envios pela API.'));
        }

        try {
            SendSpeakerAssignmentNotification::sendNowFor($assignment, $kind, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return $this->notifyError(__('Falha no envio pelo WhatsApp: :reason', ['reason' => $exception->getMessage()]));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notificação enviada ao orador.')]);

        return back();
    }

    /**
     * Send the WhatsApp notification immediately to both speakers of an exchange week
     * (visitor coming in + our speaker going out) in a single action. The
     * kind is resolved per assignment: first contact sends the assignment,
     * any follow-up sends the confirmation reminder.
     */
    public function notifyExchange(Request $request, string $current_team, string $week_start): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start) === 1, 404);

        $assignments = TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereDate('week_start', $week_start)
            ->whereIn('type', [TalkAssignmentType::Incoming, TalkAssignmentType::Outgoing])
            ->with(['speaker', 'latestNotification'])
            ->orderBy('date')
            ->get()
            ->each(fn (TalkAssignment $assignment) => $assignment->setRelation('team', $team));

        abort_if($assignments->isEmpty(), 404);

        $assignments->each(fn (TalkAssignment $assignment) => Gate::authorize('notify', $assignment));

        if (! $team->canSendWhatsappApi()) {
            return $this->notifyError(__('O WhatsApp do time não está pronto para envios pela API.'));
        }

        [$eligible, $skipped] = $assignments->partition(
            fn (TalkAssignment $assignment): bool => $assignment->speaker_id !== null
                && $assignment->outline_id !== null
                && Phone::normalize($assignment->speaker?->phone) !== null,
        );

        if ($eligible->isEmpty()) {
            return $this->notifyError(__('Nenhum orador da troca está pronto para receber a mensagem (orador, esboço e telefone).'));
        }

        $failed = collect();

        foreach ($eligible as $assignment) {
            $kind = $assignment->latestNotification === null
                ? SpeakerNotificationKind::Assignment
                : SpeakerNotificationKind::Reminder;

            try {
                SendSpeakerAssignmentNotification::sendNowFor($assignment, $kind, $request->user());
            } catch (Throwable $exception) {
                report($exception);
                $failed->push($assignment);
            }
        }

        $sent = $eligible->reject(fn (TalkAssignment $assignment): bool => $failed->contains($assignment));

        if ($sent->isEmpty()) {
            return $this->notifyError(__('O envio pelo WhatsApp falhou para os oradores da troca.'));
        }

        $sentNames = $sent
            ->map(fn (TalkAssignment $assignment): string => (string) $assignment->speaker?->name)
            ->filter()
            ->implode(' e ');

        $skipped = $skipped->merge($failed);

        if ($skipped->isEmpty()) {
            $message = $sent->count() > 1
                ? __('Mensagem enviada aos dois oradores da troca.')
                : __('Mensagem enviada para :name.', ['name' => $sentNames]);
        } else {
            $skippedNames = $skipped
                ->map(fn (TalkAssignment $assignment): string => $assignment->speaker?->name ?? __('orador não definido'))
                ->implode(' e ');

            $message = __('Mensagem enviada para :sent. Sem envio para :skipped (falta orador, esboço ou telefone, ou o envio falhou).', [
                'sent' => $sentNames,
                'skipped' => $skippedNames,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Flash an error toast and return to the schedule.
     */
    protected function notifyError(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
