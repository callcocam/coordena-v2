<?php

namespace App\Console\Commands;

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
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
 * Lembretes diários do fim de semana: D-3 ao orador do discurso `home`
 * (template `coordena_talk_reminder`, no máximo um por assignment) e D-1 ao
 * coordenador responsável com os discursos ainda não confirmados.
 */
#[Signature('public-talks:send-speaker-reminders {--dry-run : Só lista o que seria enviado, sem despachar nada}')]
#[Description('Envia o lembrete D-3 aos oradores e o alerta D-1 de discursos não confirmados')]
class SendSpeakerReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ConfiguredTeams $teams, CoordinatorAlert $alert): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $reminderDate = Carbon::today()->addDays((int) config('public_talks.reminders.speaker_days_before'));
        $pendingDate = Carbon::today()->addDays((int) config('public_talks.reminders.pending_days_before'));

        foreach ($teams->query()->cursor() as $team) {
            /** @var Team $team */
            $this->remindSpeakers($team, $reminderDate, $dryRun);
            $this->alertPending($team, $pendingDate, $alert, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * D-N: queue the `reminder` template for every notifiable `home`
     * assignment of the date that never got one (jitter between sends).
     */
    protected function remindSpeakers(Team $team, Carbon $date, bool $dryRun): void
    {
        $assignments = TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereDate('date', $date)
            ->where('type', TalkAssignmentType::Home)
            ->whereNotNull('speaker_id')
            ->whereHas('speaker', fn (Builder $query) => $query->whereNotNull('phone'))
            ->whereDoesntHave('notifications', function (Builder $query) {
                $query->where('kind', SpeakerNotificationKind::Reminder)
                    ->whereIn('status', [SpeakerNotificationStatus::Pending, SpeakerNotificationStatus::Sent]);
            })
            ->get();

        foreach ($assignments->values() as $index => $assignment) {
            if ($dryRun) {
                $this->line("[dry-run] {$team->name}: lembrete D-3 para {$assignment->speaker?->name} ({$date->toDateString()}).");

                continue;
            }

            SendSpeakerAssignmentNotification::queueFor(
                $assignment,
                SpeakerNotificationKind::Reminder,
                delaySeconds: $index * random_int(5, 15),
            );

            $this->info("{$team->name}: lembrete D-3 despachado para {$assignment->speaker?->name}.");
        }
    }

    /**
     * D-N: one single alert per team+date to the responsible coordinator
     * listing the still unconfirmed talks of the weekend.
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
            $this->line("[dry-run] {$team->name}: alerta D-1 — {$summary}");

            return;
        }

        if (! Cache::add($this->pendingAlertKey($team, $date), true, now()->addDay())) {
            return;
        }

        $alert->send($team, $summary);

        $this->info("{$team->name}: alerta D-1 enviado ({$pending->count()} pendência(s)).");
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
