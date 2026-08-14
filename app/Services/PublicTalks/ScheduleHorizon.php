<?php

namespace App\Services\PublicTalks;

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Mantém a programação "home" do time sempre com 3 meses de horizonte.
 *
 * Idempotente: pode rodar quantas vezes for preciso (manual ou agendado);
 * só cria os fins de semana que ainda não existem. Conforme o tempo passa,
 * novas execuções criam o mês que entra no horizonte.
 */
class ScheduleHorizon
{
    public const MONTHS_AHEAD = 3;

    /**
     * Ensure open home assignments exist for every upcoming weekend within
     * the horizon. Returns how many assignments were created.
     */
    public function ensure(Team $team): int
    {
        $weekday = $team->homeCongregation?->meeting_weekday ?? Carbon::SATURDAY;
        $today = Carbon::today();
        $created = 0;

        for ($offset = 0; $offset < self::MONTHS_AHEAD; $offset++) {
            $month = $today->copy()->startOfMonth()->addMonths($offset);

            foreach ($this->datesFor($month, $weekday) as $date) {
                if ($date->lt($today)) {
                    continue;
                }

                $exists = TalkAssignment::query()
                    ->where('team_id', $team->id)
                    ->whereDate('date', $date)
                    ->whereIn('type', [TalkAssignmentType::Home, TalkAssignmentType::Incoming])
                    ->exists();

                if ($exists) {
                    continue;
                }

                TalkAssignment::query()->create([
                    'team_id' => $team->id,
                    'date' => $date,
                    'type' => TalkAssignmentType::Home,
                    'status' => TalkAssignmentStatus::Open,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Every occurrence of the meeting weekday within the given month.
     *
     * @return list<Carbon>
     */
    protected function datesFor(Carbon $month, int $weekday): array
    {
        $date = $month->copy()->startOfMonth();

        if ($date->dayOfWeek !== $weekday) {
            $date = $date->next($weekday);
        }

        $dates = [];

        while ($date->month === $month->month) {
            $dates[] = $date->copy()->startOfDay();
            $date = $date->addWeek();
        }

        return $dates;
    }
}
