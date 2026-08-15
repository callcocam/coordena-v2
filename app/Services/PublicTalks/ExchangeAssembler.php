<?php

namespace App\Services\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\SpeakerNotificationKind;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
use App\Models\TalkAssignment;
use App\Models\User;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mesa de montagem da troca: o coordenador escolhe o tema de cada oferta
 * incoming, aceita/recusa oferta a oferta (a aceitação já materializa a
 * designação, a recusa devolve a semana ao pool) e por fim confirma o
 * pacote — que notifica a convidada com o resumo e dispara os avisos de
 * saída aos nossos oradores.
 */
class ExchangeAssembler
{
    public function __construct(
        protected ExchangeConfirmer $confirmer,
        protected ExchangeInviteManager $manager,
    ) {}

    /**
     * Pick the outline of an incoming offer among the ones it proposes.
     *
     * @throws ValidationException
     */
    public function chooseOutline(ExchangeOffer $offer, PublicTalkOutline $outline): void
    {
        if ($offer->direction !== 'incoming') {
            throw ValidationException::withMessages([
                'outline' => __('app.public_talks.exchange.assemble.errors.outline_incoming_only'),
            ]);
        }

        if (! in_array($offer->status, [ExchangeOfferStatus::Draft, ExchangeOfferStatus::Declined], true)) {
            throw ValidationException::withMessages([
                'outline' => __('app.public_talks.exchange.assemble.errors.locked'),
            ]);
        }

        if (! $offer->outlines()->whereKey($outline->id)->exists()) {
            throw ValidationException::withMessages([
                'outline' => __('app.public_talks.exchange.assemble.errors.outline_not_in_offer'),
            ]);
        }

        $offer->forceFill(['chosen_outline_id' => $outline->id])->save();
    }

    /**
     * Accept one offer and materialize its assignment right away.
     * Idempotent: accepting an already accepted offer is a no-op.
     *
     * @throws ValidationException
     */
    public function accept(ExchangeOffer $offer, ?User $user = null): void
    {
        if ($offer->status === ExchangeOfferStatus::Accepted) {
            return;
        }

        if (! in_array($offer->status, [ExchangeOfferStatus::Draft, ExchangeOfferStatus::Declined], true)) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.assemble.errors.locked'),
            ]);
        }

        if ($offer->direction === 'incoming' && $offer->chosen_outline_id === null) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.assemble.errors.theme_required', [
                    'speaker' => $offer->speaker->name,
                ]),
            ]);
        }

        $send = $offer->inviteSend;

        DB::transaction(function () use ($offer, $send, $user): void {
            $this->confirmer->validateOffer($offer);

            if ($offer->direction === 'incoming') {
                $this->confirmer->bookIncoming($offer, $send, $user);
            } else {
                $this->confirmer->bookOutgoing($offer, $send, $user);
            }

            $offer->forceFill(['status' => ExchangeOfferStatus::Accepted])->save();
        });
    }

    /**
     * Decline one offer, reverting its booking when it had been accepted:
     * an incoming week goes back to the open pool (visible to the round
     * robin again) and an outgoing booking releases our speaker.
     *
     * @throws ValidationException
     */
    public function decline(ExchangeOffer $offer): void
    {
        if ($offer->status === ExchangeOfferStatus::Declined) {
            return;
        }

        if ($offer->status === ExchangeOfferStatus::Confirmed) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.assemble.errors.locked'),
            ]);
        }

        DB::transaction(function () use ($offer): void {
            if ($offer->status === ExchangeOfferStatus::Accepted) {
                $this->revertBooking($offer);
            }

            $offer->forceFill(['status' => ExchangeOfferStatus::Declined])->save();
        });
    }

    /**
     * Close the package: mark the accepted offers confirmed, queue the
     * WhatsApp notices to our outgoing speakers and send the partner
     * coordinator a summary of what was accepted AND declined.
     *
     * @throws ValidationException
     */
    public function confirm(ExchangeInviteSend $send, ?User $user = null): string
    {
        $accepted = $send->offers()
            ->with(['speaker', 'chosenOutline', 'outlines'])
            ->where('status', ExchangeOfferStatus::Accepted)
            ->orderBy('target_date')
            ->get();

        if ($accepted->isEmpty()) {
            throw ValidationException::withMessages([
                'offers' => __('app.public_talks.exchange.confirm.errors.none'),
            ]);
        }

        $declined = $send->offers()
            ->with('speaker')
            ->where('status', ExchangeOfferStatus::Declined)
            ->orderBy('target_date')
            ->get();

        $invite = $send->invite;

        DB::transaction(function () use ($accepted, $send): void {
            foreach ($accepted as $offer) {
                $offer->forceFill(['status' => ExchangeOfferStatus::Confirmed])->save();
            }

            $send->forceFill([
                'status' => ExchangeInviteSendStatus::Answered,
                'answered_at' => $send->answered_at ?? now(),
            ])->save();
        });

        $this->manager->refreshStatus($invite->refresh());

        foreach ($accepted as $offer) {
            if ($offer->direction === 'outgoing') {
                $this->notifyOutgoingSpeaker($offer, $send, $user);
            }
        }

        $summary = $this->summarize($accepted, $declined, $send);

        $this->sendPartnerSummary($send, $summary);

        return $summary;
    }

    /**
     * Undo the assignment created when the offer was accepted.
     */
    protected function revertBooking(ExchangeOffer $offer): void
    {
        $send = $offer->inviteSend;

        if ($offer->direction === 'incoming') {
            $week = TalkAssignment::query()
                ->where('team_id', $send->invite->team_id)
                ->whereDate('week_start', $offer->target_date->copy()->startOfWeek(Carbon::MONDAY))
                ->where('type', TalkAssignmentType::Incoming)
                ->where('speaker_id', $offer->speaker_id)
                ->first();

            $week?->forceFill([
                'type' => TalkAssignmentType::Home,
                'speaker_id' => null,
                'outline_id' => null,
                'counterpart_congregation_id' => null,
                'status' => TalkAssignmentStatus::Open,
            ])->save();

            return;
        }

        TalkAssignment::query()
            ->where('team_id', $send->invite->team_id)
            ->whereDate('date', $this->confirmer->outgoingDateFor($offer, $send))
            ->where('type', TalkAssignmentType::Outgoing)
            ->where('speaker_id', $offer->speaker_id)
            ->where('status', TalkAssignmentStatus::Scheduled)
            ->get()
            ->each
            ->delete();
    }

    /**
     * Queue the assignment notice to our speaker who goes out.
     */
    protected function notifyOutgoingSpeaker(ExchangeOffer $offer, ExchangeInviteSend $send, ?User $user): void
    {
        $assignment = TalkAssignment::query()
            ->where('team_id', $send->invite->team_id)
            ->whereDate('date', $this->confirmer->outgoingDateFor($offer, $send))
            ->where('type', TalkAssignmentType::Outgoing)
            ->where('speaker_id', $offer->speaker_id)
            ->first();

        if ($assignment === null) {
            return;
        }

        SendSpeakerAssignmentNotification::queueFor($assignment, SpeakerNotificationKind::Assignment, $user);
    }

    /**
     * WhatsApp the summary to the partner coordinator (best effort) and
     * always keep it on the send's message log.
     */
    protected function sendPartnerSummary(ExchangeInviteSend $send, string $summary): void
    {
        $channel = 'manual';
        $wamid = null;
        $phone = Phone::normalize($send->congregation->contact_phone);

        if ($phone !== null) {
            try {
                $result = WhatsApp::for($send->invite->team)->sendSessionText($phone, $summary);
                $channel = 'whatsapp';
                $wamid = $result->messageId;
            } catch (CloudApiException $exception) {
                Log::warning('Exchange confirmation summary not delivered.', [
                    'send_id' => $send->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $send->messages()->create([
            'direction' => 'outbound',
            'channel' => $channel,
            'body' => $summary,
            'wamid' => $wamid,
        ]);
    }

    /**
     * Human summary of the package: accepted lines first, then declined.
     *
     * @param  Collection<int, ExchangeOffer>  $accepted
     * @param  Collection<int, ExchangeOffer>  $declined
     */
    protected function summarize(Collection $accepted, Collection $declined, ExchangeInviteSend $send): string
    {
        $lines = [__('app.public_talks.exchange.confirm.summary_heading', ['congregation' => $send->congregation->name])];

        foreach ($accepted as $offer) {
            $key = $offer->direction === 'incoming' ? 'summary_incoming' : 'summary_outgoing';

            $lines[] = __('app.public_talks.exchange.confirm.'.$key, [
                'date' => $offer->target_date->translatedFormat('d/m'),
                'speaker' => $offer->speaker->name,
            ]);
        }

        foreach ($declined as $offer) {
            $lines[] = __('app.public_talks.exchange.confirm.summary_declined', [
                'date' => $offer->target_date?->translatedFormat('d/m') ?? '—',
                'speaker' => $offer->speaker->name,
            ]);
        }

        return implode("\n", $lines);
    }
}
