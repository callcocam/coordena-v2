<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\TalkAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma ofertas de um envio: revalida disponibilidade, grava as ofertas
 * como confirmadas e cria/atualiza as talk_assignments correspondentes
 * (incoming na nossa grade; outgoing quando a convidada escolheu dos nossos).
 */
class ExchangeConfirmer
{
    public function __construct(protected ExchangeInviteManager $manager, protected SpeakerAvailability $availability) {}

    /**
     * Confirm the given offers of the send and return a human summary.
     *
     * @param  list<string>  $offerIds
     *
     * @throws ValidationException
     */
    public function confirm(ExchangeInviteSend $send, array $offerIds, ?User $user = null): string
    {
        $offers = $send->offers()
            ->with(['speaker', 'outlines'])
            ->whereIn('id', $offerIds)
            ->whereIn('status', [ExchangeOfferStatus::Draft, ExchangeOfferStatus::Selected])
            ->get();

        if ($offers->isEmpty()) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.none'),
            ]);
        }

        $invite = $send->invite;

        DB::transaction(function () use ($offers, $send, $user) {
            foreach ($offers as $offer) {
                $this->validateOffer($offer);

                if ($offer->direction === 'incoming') {
                    $this->bookIncoming($offer, $send, $user);
                } else {
                    $this->bookOutgoing($offer, $send, $user);
                }

                $offer->forceFill(['status' => ExchangeOfferStatus::Confirmed])->save();
            }

            $send->forceFill([
                'status' => ExchangeInviteSendStatus::Answered,
                'answered_at' => $send->answered_at ?? now(),
            ])->save();
        });

        $this->manager->refreshStatus($invite->refresh());

        return $this->summarize($offers, $send);
    }

    /**
     * Ensure the offer can still be confirmed.
     *
     * @throws ValidationException
     */
    protected function validateOffer(ExchangeOffer $offer): void
    {
        $month = $offer->inviteSend->invite->month;

        if ($offer->target_date === null) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.missing_date', ['speaker' => $offer->speaker->name]),
            ]);
        }

        if (! $offer->target_date->betweenIncluded($month->copy()->startOfMonth(), $month->copy()->endOfMonth())) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.outside_month', ['speaker' => $offer->speaker->name]),
            ]);
        }

        if (! $this->availability->canGoOut($offer->speaker, $offer->target_date)) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.unavailable', ['speaker' => $offer->speaker->name]),
            ]);
        }

        if ($offer->direction === 'outgoing' && $offer->inviteSend->congregation->meeting_weekday === null) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.partner_missing_schedule', [
                    'congregation' => $offer->inviteSend->congregation->name,
                ]),
            ]);
        }
    }

    /**
     * Fill our open week with the visiting speaker.
     *
     * @throws ValidationException
     */
    protected function bookIncoming(ExchangeOffer $offer, ExchangeInviteSend $send, ?User $user): void
    {
        $week = TalkAssignment::query()
            ->where('team_id', $send->invite->team_id)
            ->whereDate('week_start', $offer->target_date->copy()->startOfWeek(Carbon::MONDAY))
            ->whereIn('type', [TalkAssignmentType::Home, TalkAssignmentType::Incoming])
            ->first();

        if ($week === null || $week->status !== TalkAssignmentStatus::Open) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.week_taken', [
                    'date' => $offer->target_date->translatedFormat('d/m'),
                ]),
            ]);
        }

        $week->forceFill([
            'type' => TalkAssignmentType::Incoming,
            'speaker_id' => $offer->speaker_id,
            'outline_id' => $offer->outlines->first()?->id,
            'counterpart_congregation_id' => $send->congregation_id,
            'status' => TalkAssignmentStatus::Scheduled,
            'created_by_id' => $week->created_by_id ?? $user?->id,
        ])->save();
    }

    /**
     * Register that one of our speakers goes out to the partner congregation.
     */
    protected function bookOutgoing(ExchangeOffer $offer, ExchangeInviteSend $send, ?User $user): void
    {
        $horizon = app(ScheduleHorizon::class);
        $weekStart = $offer->target_date->copy()->startOfWeek(Carbon::MONDAY);
        $date = $horizon->meetingDateFor($weekStart, $send->congregation->meeting_weekday);

        TalkAssignment::query()->updateOrCreate([
            'team_id' => $send->invite->team_id,
            'date' => $date->toDateString(),
            'type' => TalkAssignmentType::Outgoing,
            'speaker_id' => $offer->speaker_id,
        ], [
            'outline_id' => $offer->outlines->first()?->id,
            'counterpart_congregation_id' => $send->congregation_id,
            'status' => TalkAssignmentStatus::Scheduled,
            'created_by_id' => $user?->id,
        ]);
    }

    /**
     * Human-readable confirmation summary of what was booked.
     *
     * @param  Collection<int, ExchangeOffer>  $offers
     */
    protected function summarize(Collection $offers, ExchangeInviteSend $send): string
    {
        $lines = [__('app.public_talks.exchange.confirm.summary_heading', ['congregation' => $send->congregation->name])];

        foreach ($offers as $offer) {
            $key = $offer->direction === 'incoming' ? 'summary_incoming' : 'summary_outgoing';

            $lines[] = __('app.public_talks.exchange.confirm.'.$key, [
                'date' => $offer->target_date->translatedFormat('d/m'),
                'speaker' => $offer->speaker->name,
            ]);
        }

        return implode("\n", $lines);
    }
}
