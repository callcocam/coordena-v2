<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\ExchangeInvite;
use App\Models\Team;
use App\Models\User;

class ExchangeInvitePolicy
{
    /**
     * Determine whether the user can view the exchange invites of a team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can view the invite.
     */
    public function view(User $user, ExchangeInvite $invite): bool
    {
        return $user->hasTeamPermission($invite->team, TeamPermission::ViewPublicTalks);
    }

    /**
     * Determine whether the user can create invites for a team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can update the invite.
     */
    public function update(User $user, ExchangeInvite $invite): bool
    {
        return $user->hasTeamPermission($invite->team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can delete the invite.
     */
    public function delete(User $user, ExchangeInvite $invite): bool
    {
        return $user->hasTeamPermission($invite->team, TeamPermission::ManagePublicTalks);
    }

    /**
     * Determine whether the user can send the invite to congregations.
     */
    public function send(User $user, ExchangeInvite $invite): bool
    {
        return $user->hasTeamPermission($invite->team, TeamPermission::NotifyPublicTalks);
    }
}
