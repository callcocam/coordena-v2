<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappTermsAcceptance;

/**
 * The WhatsApp connection terms of use. The version is bumped whenever the
 * legal text (lang/pt_BR/app/whatsapp.php → app.whatsapp.terms) changes, which
 * forces managers to accept again before connecting.
 */
class WhatsappTerms
{
    /**
     * Current terms version. Bump this (e.g. to '2026-08-01') whenever the
     * terms text changes so a fresh acceptance is required.
     */
    public const VERSION = '2026-07-10';

    /**
     * Whether the user has accepted the current terms version for the team.
     */
    public static function accepted(User $user, Team $team): bool
    {
        return WhatsappTermsAcceptance::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('version', self::VERSION)
            ->exists();
    }

    /**
     * Whether any manager has accepted the current terms version for the team.
     * This is the team-level "activated" gate enforced on every send: no
     * automatic message goes out (own or shared number) until someone accepts.
     */
    public static function acceptedByTeam(Team $team): bool
    {
        return WhatsappTermsAcceptance::query()
            ->where('team_id', $team->id)
            ->where('version', self::VERSION)
            ->exists();
    }
}
