<?php

namespace App\Services\PublicTalks;

use App\Enums\CoordinatorRole;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

/**
 * Times com o módulo de discursos públicos configurado: congregação de casa
 * definida e coordenador responsável ativo. É sobre esses times que o
 * scheduler (fase 6) atua.
 */
class ConfiguredTeams
{
    /**
     * Query of every team with the public talks module configured.
     *
     * @return Builder<Team>
     */
    public function query(): Builder
    {
        return Team::query()
            ->whereNotNull('home_congregation_id')
            ->whereHas('coordinators', function (Builder $query) {
                $query->active()->where('role', CoordinatorRole::Responsible);
            });
    }
}
