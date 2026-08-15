<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\ExchangeOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SaveExchangeOfferRequest;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
use App\Services\PublicTalks\ExchangeAssembler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExchangeOfferController extends Controller
{
    public function __construct(protected ExchangeAssembler $assembler) {}

    /**
     * Register a draft offer on the send's workbench.
     */
    public function store(SaveExchangeOfferRequest $request, string $current_team, ExchangeInviteSend $send): RedirectResponse
    {
        $this->authorizeSend($request, $send);

        $offer = $send->offers()->create([
            'direction' => $request->string('direction')->value(),
            'speaker_id' => $request->string('speaker_id')->value(),
            'target_date' => $request->date('target_date'),
            'status' => ExchangeOfferStatus::Draft,
            'source_message_id' => $request->input('source_message_id'),
            'created_by_id' => $request->user()->id,
        ]);

        $offer->outlines()->sync($request->input('outline_ids', []));

        return back();
    }

    /**
     * Update a draft offer (speaker, week or outlines).
     */
    public function update(SaveExchangeOfferRequest $request, string $current_team, ExchangeInviteSend $send, ExchangeOffer $offer): RedirectResponse
    {
        $this->authorizeSend($request, $send);
        abort_unless($offer->invite_send_id === $send->id, 404);
        abort_unless($offer->status === ExchangeOfferStatus::Draft, 422);

        $offer->update([
            'direction' => $request->string('direction')->value(),
            'speaker_id' => $request->string('speaker_id')->value(),
            'target_date' => $request->date('target_date'),
        ]);

        $offer->outlines()->sync($request->input('outline_ids', []));

        return back();
    }

    /**
     * Discard an offer that will not be used.
     */
    public function destroy(Request $request, string $current_team, ExchangeInviteSend $send, ExchangeOffer $offer): RedirectResponse
    {
        $this->authorizeSend($request, $send);
        abort_unless($offer->invite_send_id === $send->id, 404);

        Gate::authorize('update', $send->invite);

        if ($offer->status === ExchangeOfferStatus::Confirmed) {
            abort(422);
        }

        $offer->forceFill(['status' => ExchangeOfferStatus::Discarded])->save();

        return back();
    }

    /**
     * Pick the outline of an incoming offer.
     */
    public function chooseOutline(Request $request, string $current_team, ExchangeInviteSend $send, ExchangeOffer $offer): RedirectResponse
    {
        $this->authorizeSend($request, $send);
        abort_unless($offer->invite_send_id === $send->id, 404);

        Gate::authorize('update', $send->invite);

        $validated = $request->validate([
            'outline_id' => ['required', 'string'],
        ]);

        $outline = PublicTalkOutline::query()->findOrFail($validated['outline_id']);

        $this->assembler->chooseOutline($offer, $outline);

        return back();
    }

    /**
     * Accept a single offer, booking its assignment right away.
     */
    public function accept(Request $request, string $current_team, ExchangeInviteSend $send, ExchangeOffer $offer): RedirectResponse
    {
        $this->authorizeSend($request, $send);
        abort_unless($offer->invite_send_id === $send->id, 404);

        Gate::authorize('update', $send->invite);

        $this->assembler->accept($offer, $request->user());

        return back();
    }

    /**
     * Decline a single offer, releasing its booking when needed.
     */
    public function decline(Request $request, string $current_team, ExchangeInviteSend $send, ExchangeOffer $offer): RedirectResponse
    {
        $this->authorizeSend($request, $send);
        abort_unless($offer->invite_send_id === $send->id, 404);

        Gate::authorize('update', $send->invite);

        $this->assembler->decline($offer);

        return back();
    }

    /**
     * Ensure the send belongs to the current team.
     */
    protected function authorizeSend(Request $request, ExchangeInviteSend $send): void
    {
        abort_unless($send->invite->team_id === $request->user()->currentTeam?->id, 404);
    }
}
