<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Speaker;
use App\Models\User;

class SpeakerPolicy
{
    /**
     * Determine whether the user can view speakers of the current acervo.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, [TeamPermission::ViewCongregations, TeamPermission::ViewPublicTalks]);
    }

    /**
     * Determine whether the user can view the speaker.
     */
    public function view(User $user, Speaker $speaker): bool
    {
        return $this->ownsAcervo($user, $speaker)
            && $this->hasAny($user, [TeamPermission::ViewCongregations, TeamPermission::ViewPublicTalks]);
    }

    /**
     * Determine whether the user can create speakers.
     *
     * Regra especial: quem gerencia discursos também pode cadastrar oradores
     * rapidamente, sem precisar de `congregations:manage`.
     */
    public function create(User $user): bool
    {
        return $this->hasAny($user, [TeamPermission::ManageCongregations, TeamPermission::ManagePublicTalks]);
    }

    /**
     * Determine whether the user can update the speaker.
     */
    public function update(User $user, Speaker $speaker): bool
    {
        return $this->ownsAcervo($user, $speaker)
            && $this->hasAny($user, [TeamPermission::ManageCongregations, TeamPermission::ManagePublicTalks]);
    }

    /**
     * Determine whether the user can delete the speaker.
     */
    public function delete(User $user, Speaker $speaker): bool
    {
        return $this->ownsAcervo($user, $speaker)
            && $this->hasAny($user, [TeamPermission::ManageCongregations, TeamPermission::ManagePublicTalks]);
    }

    /**
     * Whether the user holds any of the permissions on the current team.
     *
     * @param  list<TeamPermission>  $permissions
     */
    protected function hasAny(User $user, array $permissions): bool
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasTeamPermission($team, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the speaker belongs to the acervo of the current team's owner.
     */
    protected function ownsAcervo(User $user, Speaker $speaker): bool
    {
        return $user->currentTeam?->owner()?->id === $speaker->congregation->owner_user_id;
    }
}
