<?php

namespace App\Services\PublicTalks;

use App\Enums\CoordinatorRole;
use App\Models\Coordinator;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolve quem é o coordenador responsável do time e quem deve receber
 * as comunicações do módulo de discursos.
 */
class ResponsibleCoordinator
{
    /**
     * The active responsible coordinator of the team, when any.
     */
    public function for(Team $team): ?Coordinator
    {
        return $team->coordinators()
            ->active()
            ->where('role', CoordinatorRole::Responsible)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Every active coordinator with a phone, responsible first, that should
     * receive notifications for the team.
     *
     * @return Collection<int, Coordinator>
     */
    public function recipientsFor(Team $team): Collection
    {
        return $team->coordinators()
            ->active()
            ->whereNotNull('phone')
            ->get()
            ->sortBy(fn (Coordinator $coordinator): int => $coordinator->role === CoordinatorRole::Responsible ? 0 : 1)
            ->values();
    }
}
