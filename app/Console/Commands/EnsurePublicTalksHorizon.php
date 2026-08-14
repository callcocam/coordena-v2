<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\PublicTalks\ConfiguredTeams;
use App\Services\PublicTalks\ExchangeInviteManager;
use App\Services\PublicTalks\ScheduleHorizon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Mantém o horizonte contínuo de 3 meses da programação sem depender de
 * visita à tela: para cada time configurado roda o {@see ScheduleHorizon}
 * e garante o convite de troca de cada mês do horizonte.
 */
#[Signature('public-talks:ensure-horizon {--dry-run : Só lista o que seria feito, sem gravar nada}')]
#[Description('Garante o horizonte de 3 meses da programação e o convite de troca de cada mês')]
class EnsurePublicTalksHorizon extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        ConfiguredTeams $teams,
        ScheduleHorizon $horizon,
        ExchangeInviteManager $invites,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        foreach ($teams->query()->with('homeCongregation')->cursor() as $team) {
            /** @var Team $team */
            if ($dryRun) {
                $this->line("[dry-run] {$team->name}: garantiria horizonte de "
                    .ScheduleHorizon::MONTHS_AHEAD.' meses e convites mensais.');

                continue;
            }

            $created = $horizon->ensure($team);

            foreach ($this->horizonMonths() as $month) {
                $invites->forMonth($team, $month);
            }

            $this->info("{$team->name}: {$created} fim(ns) de semana criado(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Every month inside the schedule horizon, current month first.
     *
     * @return array<int, Carbon>
     */
    protected function horizonMonths(): array
    {
        $months = [];

        for ($offset = 0; $offset < ScheduleHorizon::MONTHS_AHEAD; $offset++) {
            $months[] = Carbon::today()->startOfMonth()->addMonths($offset)->startOfDay();
        }

        return $months;
    }
}
