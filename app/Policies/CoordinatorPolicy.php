<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Coordinator;
use App\Models\Team;
use App\Models\User;

class CoordinatorPolicy
{
    /**
     * Determine whether the user can view the coordinators of a team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can view the coordinator.
     */
    public function view(User $user, Coordinator $coordinator): bool
    {
        return $user->hasTeamPermission($coordinator->team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can create coordinators for a team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can update the coordinator.
     */
    public function update(User $user, Coordinator $coordinator): bool
    {
        return $user->hasTeamPermission($coordinator->team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can delete the coordinator.
     */
    public function delete(User $user, Coordinator $coordinator): bool
    {
        return $user->hasTeamPermission($coordinator->team, TeamPermission::ManagePublicTalks);
    }
}
