<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeOfferStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\Congregation;
use App\Models\ExchangeOffer;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Disponibilidade de oradores para sair em um mês.
 *
 * A checagem roda sobre o acervo do dono (sem escopo de time): um orador
 * ocupado por qualquer time do mesmo dono não é oferecido de novo.
 */
class SpeakerAvailability
{
    /**
     * Whether the speaker is free to go out in the given month.
     */
    public function canGoOut(Speaker $speaker, CarbonInterface $month): bool
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $hasBookedTalk = TalkAssignment::query()
            ->where('speaker_id', $speaker->id)
            ->whereIn('type', [TalkAssignmentType::Outgoing, TalkAssignmentType::Incoming])
            ->whereIn('status', [
                TalkAssignmentStatus::Scheduled,
                TalkAssignmentStatus::Notified,
                TalkAssignmentStatus::Confirmed,
            ])
            ->whereBetween('date', [$start, $end])
            ->exists();

        if ($hasBookedTalk) {
            return false;
        }

        return ! ExchangeOffer::query()
            ->where('speaker_id', $speaker->id)
            ->whereIn('status', [ExchangeOfferStatus::Selected, ExchangeOfferStatus::Accepted, ExchangeOfferStatus::Confirmed])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('target_date', [$start, $end])
                    ->orWhere(function ($query) use ($start) {
                        $query->whereNull('target_date')
                            ->whereHas('inviteSend.invite', fn ($invite) => $invite->where('month', $start));
                    });
            })
            ->exists();
    }

    /**
     * Active speakers of the congregation free to go out in the month,
     * with their outlines loaded.
     *
     * @return Collection<int, Speaker>
     */
    public function availableFor(Congregation $congregation, CarbonInterface $month): Collection
    {
        return $congregation->speakers()
            ->active()
            ->with('outlines')
            ->get()
            ->filter(fn (Speaker $speaker): bool => $this->canGoOut($speaker, $month))
            ->values();
    }
}
