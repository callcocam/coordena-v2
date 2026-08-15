<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOpt;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Rodízio de congregações parceiras para o convite mensal.
 *
 * Sugere a próxima candidata (somente congregações que aceitaram trocas —
 * `opted_in` após a apresentação —, com contato, sem envio vivo neste
 * convite, menos recentemente convidada) — sugestão, não imposição: o
 * coordenador escolhe e envia manualmente.
 */
class ExchangeRoundRobin
{
    /**
     * Send statuses that still hold a congregation for their invite.
     */
    protected const LIVE_STATUSES = [
        ExchangeInviteSendStatus::Pending,
        ExchangeInviteSendStatus::Sent,
        ExchangeInviteSendStatus::Accepted,
        ExchangeInviteSendStatus::Answered,
    ];

    /**
     * The suggested next partner congregation for the invite, when any.
     */
    public function nextFor(ExchangeInvite $invite): ?Congregation
    {
        return $this->candidatesFor($invite)->first();
    }

    /**
     * Every eligible partner, least recently invited first.
     *
     * Partners with a live send on another month of the horizon are pushed
     * out so each month suggests a different congregation; when every
     * eligible partner is already engaged elsewhere, the full rotation is
     * kept as a fallback instead of going silent.
     *
     * @return Collection<int, Congregation>
     */
    public function candidatesFor(ExchangeInvite $invite): Collection
    {
        $team = $invite->team;
        $owner = $team->owner();

        if ($owner === null) {
            return new Collection;
        }

        $liveCongregationIds = $invite->sends()
            ->whereIn('status', self::LIVE_STATUSES)
            ->pluck('congregation_id');

        $lastInvitedAt = $this->lastInvitedAtByCongregation($invite);

        $eligible = Congregation::query()
            ->ownedBy($owner->id)
            ->where('exchange_opt', ExchangeOpt::OptedIn)
            ->whereKeyNot($liveCongregationIds->all())
            ->when($team->home_congregation_id !== null, fn ($query) => $query->whereKeyNot($team->home_congregation_id))
            ->where(function ($query) {
                $query->whereNotNull('contact_phone')
                    ->orWhereNotNull('secretary_phone')
                    ->orWhereNotNull('contact_email')
                    ->orWhereNotNull('secretary_email');
            })
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Congregation $congregation) => $lastInvitedAt[$congregation->id] ?? '')
            ->values();

        $engagedElsewhere = $this->liveElsewhereCongregationIds($invite);

        $available = $eligible->reject(
            fn (Congregation $congregation): bool => in_array($congregation->id, $engagedElsewhere, true)
        )->values();

        return $available->isNotEmpty() ? $available : $eligible;
    }

    /**
     * Congregations with a live send on another invite of the team within
     * the schedule horizon.
     *
     * @return list<string>
     */
    protected function liveElsewhereCongregationIds(ExchangeInvite $invite): array
    {
        $horizonStart = Carbon::today()->startOfMonth();
        $horizonEnd = $horizonStart->copy()->addMonths(ScheduleHorizon::MONTHS_AHEAD - 1);

        return ExchangeInviteSend::query()
            ->whereIn('status', self::LIVE_STATUSES)
            ->whereHas('invite', fn ($query) => $query
                ->where('team_id', $invite->team_id)
                ->whereKeyNot($invite->id)
                ->whereBetween('month', [$horizonStart, $horizonEnd]))
            ->pluck('congregation_id')
            ->all();
    }

    /**
     * Partners with contact info still waiting for the introduction opt-in
     * (`unknown`) — shown by the exchange page so an empty rotation explains
     * itself instead of looking broken.
     *
     * @return Collection<int, Congregation>
     */
    public function pendingIntroFor(ExchangeInvite $invite): Collection
    {
        $team = $invite->team;
        $owner = $team->owner();

        if ($owner === null) {
            return new Collection;
        }

        return Congregation::query()
            ->ownedBy($owner->id)
            ->where('exchange_opt', ExchangeOpt::Unknown)
            ->when($team->home_congregation_id !== null, fn ($query) => $query->whereKeyNot($team->home_congregation_id))
            ->where(function ($query) {
                $query->whereNotNull('contact_phone')
                    ->orWhereNotNull('secretary_phone')
                    ->orWhereNotNull('contact_email')
                    ->orWhereNotNull('secretary_email');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * When each congregation was last invited by this team, keyed by id.
     *
     * @return array<string, string>
     */
    public function lastInvitedAtByCongregation(ExchangeInvite $invite): array
    {
        return ExchangeInviteSend::query()
            ->whereHas('invite', fn ($query) => $query->where('team_id', $invite->team_id))
            ->whereNotNull('sent_at')
            ->get(['congregation_id', 'sent_at'])
            ->groupBy('congregation_id')
            ->map(fn (Collection $sends): string => $sends->max('sent_at')?->toDateTimeString() ?? Carbon::now()->toDateTimeString())
            ->all();
    }
}
