<?php

namespace App\Console\Commands;

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ConfiguredTeams;
use App\Services\PublicTalks\CoordinatorAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Lembretes diários do fim de semana, em três passadas na mesma execução:
 * D-3 ao orador de qualquer direção (home, quem sai e o visitante que vem),
 * D-1 de novo a quem ainda não confirmou, e D-0 o alerta ao coordenador
 * responsável com os discursos do dia sem confirmação.
 *
 * A dedupe é por dia: no máximo um lembrete automático por assignment por
 * execução diária, e um lembrete manual disparado hoje suprime o automático
 * de hoje (sem ping duplo). Confirmados e remarcações ficam de fora.
 */
#[Signature('public-talks:send-speaker-reminders {--dry-run : Só lista o que seria enviado, sem despachar nada}')]
#[Description('Envia os lembretes D-3/D-1 aos oradores e o alerta D-0 de discursos não confirmados')]
class SendSpeakerReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ConfiguredTeams $teams, CoordinatorAlert $alert): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $reminderOffsets = array_unique([
            (int) config('public_talks.reminders.speaker_days_before'),
            (int) config('public_talks.reminders.speaker_second_days_before'),
        ]);
        $pendingDate = Carbon::today()->addDays((int) config('public_talks.reminders.pending_days_before'));

        foreach ($teams->query()->cursor() as $team) {
            /** @var Team $team */
            foreach ($reminderOffsets as $daysBefore) {
                $this->remindSpeakers($team, Carbon::today()->addDays($daysBefore), $dryRun);
            }

            $this->alertPending($team, $pendingDate, $alert, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * D-N: queue the `reminder` template for every notifiable assignment of
     * the date — any direction, the visitor included — skipping confirmed
     * talks, reschedules, and anyone already reminded today (jitter between
     * sends).
     */
    protected function remindSpeakers(Team $team, Carbon $date, bool $dryRun): void
    {
        $daysBefore = (int) Carbon::today()->diffInDays($date);

        $assignments = TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereDate('date', $date)
            ->whereNotIn('status', [TalkAssignmentStatus::Confirmed, TalkAssignmentStatus::NeedsReschedule])
            ->whereNotNull('speaker_id')
            ->whereNotNull('outline_id')
            ->whereHas('speaker', fn (Builder $query) => $query->whereNotNull('phone'))
            ->whereDoesntHave('notifications', function (Builder $query) {
                $query->where('kind', SpeakerNotificationKind::Reminder)
                    ->whereIn('status', [SpeakerNotificationStatus::Pending, SpeakerNotificationStatus::Sent])
                    ->where('created_at', '>=', Carbon::today()->startOfDay());
            })
            ->get();

        foreach ($assignments->values() as $index => $assignment) {
            if ($dryRun) {
                $this->line("[dry-run] {$team->name}: lembrete D-{$daysBefore} para {$assignment->speaker?->name} ({$date->toDateString()}).");

                continue;
            }

            SendSpeakerAssignmentNotification::queueFor(
                $assignment,
                SpeakerNotificationKind::Reminder,
                delaySeconds: $index * random_int(5, 15),
            );

            $this->info("{$team->name}: lembrete D-{$daysBefore} despachado para {$assignment->speaker?->name}.");
        }
    }

    /**
     * D-N: one single alert per team+date to the responsible coordinator
     * listing the still unconfirmed talks of the date.
     */
    protected function alertPending(Team $team, Carbon $date, CoordinatorAlert $alert, bool $dryRun): void
    {
        $pending = TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereDate('date', $date)
            ->where('status', '!=', TalkAssignmentStatus::Confirmed)
            ->with(['speaker', 'outline'])
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $summary = $this->pendingSummary($pending, $date);

        if ($dryRun) {
            $this->line("[dry-run] {$team->name}: alerta D-0 — {$summary}");

            return;
        }

        if (! Cache::add($this->pendingAlertKey($team, $date), true, now()->addDay())) {
            return;
        }

        $alert->send($team, $summary);

        $this->info("{$team->name}: alerta D-0 enviado ({$pending->count()} pendência(s)).");
    }

    /**
     * Human summary of the unconfirmed talks for the coordinator alert.
     *
     * @param  Collection<int, TalkAssignment>  $pending
     */
    protected function pendingSummary(Collection $pending, Carbon $date): string
    {
        $lines = $pending
            ->map(function (TalkAssignment $assignment): string {
                $speaker = $assignment->speaker?->name ?? 'sem orador';
                $outline = $assignment->outline?->title;

                return $outline === null ? $speaker : "{$speaker} ({$outline})";
            })
            ->implode('; ');

        return "{$pending->count()} discurso(s) de {$date->translatedFormat('d/m')} ainda sem confirmação: {$lines}.";
    }

    /**
     * Once-a-day guard key so a re-run does not repeat the alert.
     */
    protected function pendingAlertKey(Team $team, Carbon $date): string
    {
        return "public-talks:pending-alert:{$team->id}:{$date->toDateString()}";
    }
}
