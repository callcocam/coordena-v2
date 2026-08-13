<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Congregation;
use App\Models\User;

class CongregationPolicy
{
    /**
     * Determine whether the user can view the acervo of the current team.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewCongregations);
    }

    /**
     * Determine whether the user can view the congregation.
     */
    public function view(User $user, Congregation $congregation): bool
    {
        return $this->allows($user, $congregation, TeamPermission::ViewCongregations);
    }

    /**
     * Determine whether the user can create congregations in the acervo.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageCongregations);
    }

    /**
     * Determine whether the user can update the congregation.
     */
    public function update(User $user, Congregation $congregation): bool
    {
        return $this->allows($user, $congregation, TeamPermission::ManageCongregations);
    }

    /**
     * Determine whether the user can delete the congregation.
     */
    public function delete(User $user, Congregation $congregation): bool
    {
        return $this->allows($user, $congregation, TeamPermission::ManageCongregations);
    }

    /**
     * O acervo pertence ao dono do time atual: além da permissão no time,
     * a congregação precisa pertencer ao acervo desse dono.
     */
    protected function allows(User $user, Congregation $congregation, TeamPermission $permission): bool
    {
        $team = $user->currentTeam;

        if ($team === null || ! $user->hasTeamPermission($team, $permission)) {
            return false;
        }

        return $team->owner()?->id === $congregation->owner_user_id;
    }
}
