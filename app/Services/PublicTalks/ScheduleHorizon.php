<?php

namespace App\Services\PublicTalks;

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Mantém a programação "home" do time sempre com 3 meses de horizonte.
 *
 * Idempotente POR SEMANA (segunda-feira ISO como chave): cada semana do
 * horizonte tem no máximo 1 slot home|incoming, e a data concreta da reunião
 * é derivada do `meeting_weekday` da congregação-casa. Se o dia/horário da
 * casa mudar, slots `open` futuros têm a data recalculada; slots já
 * preenchidos não mudam sozinhos.
 */
class ScheduleHorizon
{
    public const MONTHS_AHEAD = 3;

    /**
     * Ensure one open home assignment exists for every upcoming week within
     * the horizon. Returns how many assignments were created.
     */
    public function ensure(Team $team): int
    {
        $weekday = $this->homeWeekday($team);
        $today = Carbon::today();
        $horizonEnd = $today->copy()->startOfMonth()->addMonths(self::MONTHS_AHEAD)->subDay();
        $created = 0;

        $this->recalculateOpenDates($team, $weekday);

        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        while ($weekStart->lte($horizonEnd)) {
            $date = $this->meetingDateFor($weekStart, $weekday);

            if ($date->lt($today) || $date->gt($horizonEnd)) {
                $weekStart = $weekStart->copy()->addWeek();

                continue;
            }

            $exists = TalkAssignment::query()
                ->where('team_id', $team->id)
                ->whereDate('week_start', $weekStart)
                ->whereIn('type', [TalkAssignmentType::Home, TalkAssignmentType::Incoming])
                ->exists();

            if (! $exists) {
                TalkAssignment::query()->create([
                    'team_id' => $team->id,
                    'date' => $date,
                    'type' => TalkAssignmentType::Home,
                    'status' => TalkAssignmentStatus::Open,
                ]);

                $created++;
            }

            $weekStart = $weekStart->copy()->addWeek();
        }

        return $created;
    }

    /**
     * The concrete meeting date of a week: `week_start` + the congregation's
     * meeting weekday (semana ISO: segunda a domingo).
     */
    public function meetingDateFor(CarbonInterface $weekStart, int $weekday): Carbon
    {
        $offset = $weekday === Carbon::SUNDAY ? 6 : $weekday - 1;

        return Carbon::instance($weekStart)->startOfWeek(Carbon::MONDAY)->addDays($offset)->startOfDay();
    }

    /**
     * The meeting weekday of the team's home congregation.
     */
    public function homeWeekday(Team $team): int
    {
        return $team->homeCongregation?->meeting_weekday ?? Carbon::SATURDAY;
    }

    /**
     * Re-derive the concrete date of future OPEN home slots after the home
     * congregation's meeting weekday changed. Filled slots keep their date
     * (o coordenador revisa manualmente).
     */
    protected function recalculateOpenDates(Team $team, int $weekday): void
    {
        $today = Carbon::today();

        TalkAssignment::query()
            ->where('team_id', $team->id)
            ->where('type', TalkAssignmentType::Home)
            ->where('status', TalkAssignmentStatus::Open)
            ->whereDate('week_start', '>=', $today->copy()->startOfWeek(Carbon::MONDAY))
            ->get()
            ->each(function (TalkAssignment $assignment) use ($weekday, $today): void {
                $date = $this->meetingDateFor($assignment->week_start, $weekday);

                if ($date->lt($today) || $assignment->date->equalTo($date)) {
                    return;
                }

                $assignment->forceFill(['date' => $date])->save();
            });
    }
}
