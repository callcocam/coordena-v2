<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeInviteStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\ExchangeInvite;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Ciclo de vida do convite mensal de troca: 1 convite por time+mês,
 * cobrindo as semanas em falta daquele mês. Nunca é recriado.
 */
class ExchangeInviteManager
{
    /**
     * The single invite of the team for the month (created on first access).
     */
    public function forMonth(Team $team, CarbonInterface $month, ?User $creator = null): ExchangeInvite
    {
        $invite = ExchangeInvite::query()->firstOrCreate([
            'team_id' => $team->id,
            'month' => $month->copy()->startOfMonth()->startOfDay(),
        ], [
            'status' => ExchangeInviteStatus::Open,
            'created_by_id' => $creator?->id,
        ]);

        return $this->refreshStatus($invite);
    }

    /**
     * The upcoming weeks of the invite month still without a speaker.
     *
     * @return Collection<int, TalkAssignment>
     */
    public function openWeeks(ExchangeInvite $invite): Collection
    {
        return $this->monthWeeks($invite)
            ->filter(fn (TalkAssignment $week): bool => $week->status === TalkAssignmentStatus::Open)
            ->values();
    }

    /**
     * Recalculate the invite status from its month weeks.
     */
    public function refreshStatus(ExchangeInvite $invite): ExchangeInvite
    {
        $status = $this->statusFor($invite);

        if ($invite->status !== $status) {
            $invite->forceFill(['status' => $status])->save();
        }

        return $invite;
    }

    /**
     * The status the invite should have right now.
     */
    protected function statusFor(ExchangeInvite $invite): ExchangeInviteStatus
    {
        if ($invite->month->copy()->endOfMonth()->isPast()) {
            return ExchangeInviteStatus::Expired;
        }

        $weeks = $this->monthWeeks($invite);
        $open = $weeks->filter(fn (TalkAssignment $week): bool => $week->status === TalkAssignmentStatus::Open);

        if ($weeks->isNotEmpty() && $open->isEmpty()) {
            return ExchangeInviteStatus::Filled;
        }

        if ($open->count() < $weeks->count()) {
            return ExchangeInviteStatus::PartiallyFilled;
        }

        return ExchangeInviteStatus::Open;
    }

    /**
     * The upcoming home-side weeks (home or incoming) of the invite month.
     *
     * @return Collection<int, TalkAssignment>
     */
    protected function monthWeeks(ExchangeInvite $invite): Collection
    {
        $start = $invite->month->copy()->startOfMonth()->max(Carbon::today());

        return TalkAssignment::query()
            ->where('team_id', $invite->team_id)
            ->whereIn('type', [TalkAssignmentType::Home, TalkAssignmentType::Incoming])
            ->whereBetween('date', [$start, $invite->month->copy()->endOfMonth()])
            ->orderBy('date')
            ->get();
    }
}
