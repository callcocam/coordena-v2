<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;

class TalkAssignmentPolicy
{
    /**
     * Determine whether the user can view the schedule of a team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can view the assignment.
     */
    public function view(User $user, TalkAssignment $assignment): bool
    {
        return $user->hasTeamPermission($assignment->team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can create assignments for a team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can update the assignment.
     */
    public function update(User $user, TalkAssignment $assignment): bool
    {
        return $user->hasTeamPermission($assignment->team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can delete the assignment.
     */
    public function delete(User $user, TalkAssignment $assignment): bool
    {
        return $user->hasTeamPermission($assignment->team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can notify the speaker of the assignment.
     */
    public function notify(User $user, TalkAssignment $assignment): bool
    {
        return $user->hasTeamPermission($assignment->team, TeamPermission::NotifyPublicTalks);
    }
}
