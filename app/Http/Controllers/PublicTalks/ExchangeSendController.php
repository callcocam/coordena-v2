<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\PublicTalkOutlineStatus;
use App\Http\Controllers\Controller;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\ExchangeAssembler;
use App\Services\PublicTalks\ExchangeInviteManager;
use App\Services\PublicTalks\SpeakerAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeSendController extends Controller
{
    public function __construct(
        protected ExchangeInviteManager $manager,
        protected ExchangeAssembler $assembler,
        protected SpeakerAvailability $availability,
    ) {}

    /**
     * Show the workbench of a send: offers, messages and portal link.
     */
    public function show(Request $request, string $current_team, ExchangeInviteSend $send): Response
    {
        $this->authorizeSend($request, $send);

        Gate::authorize('view', $send->invite);

        $invite = $send->invite;
        $home = $request->user()->currentTeam->homeCongregation;

        return Inertia::render('publicTalks/ExchangeWorkbench', [
            'month' => $invite->month->format('Y-m'),
            'send' => [
                'id' => $send->id,
                'status' => $send->status->value,
                'sent_at' => $send->sent_at?->toDateTimeString(),
                'answered_at' => $send->answered_at?->toDateTimeString(),
                'portal_url' => route('exchange.portal', $send->portal_token),
                'congregation' => [
                    'id' => $send->congregation->id,
                    'name' => $send->congregation->name,
                    'meeting_weekday' => $send->congregation->meeting_weekday,
                    'meeting_time' => $send->congregation->meeting_time,
                ],
            ],
            'offers' => $this->offersFor($send),
            'messages' => $send->messages()->orderBy('created_at')->get()
                ->map(fn (ExchangeMessage $message): array => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toDateTimeString(),
                ])->all(),
            'openWeeks' => $this->manager->openWeeks($invite)
                ->map(fn (TalkAssignment $week): array => [
                    'id' => $week->id,
                    'date' => $week->date->toDateString(),
                ])->all(),
            'counterpartSpeakers' => $this->speakersOf($send->congregation_id),
            'homeSpeakers' => $home !== null ? $this->speakersOf($home->id) : [],
            'outlines' => PublicTalkOutline::query()
                ->where('status', PublicTalkOutlineStatus::Active)
                ->orderBy('number')
                ->get()
                ->map(fn (PublicTalkOutline $outline): array => [
                    'id' => $outline->id,
                    'number' => $outline->number,
                    'title' => $outline->title,
                ])->all(),
            'canManage' => Gate::allows('update', $invite),
        ]);
    }

    /**
     * Register a pasted reply from the partner congregation.
     */
    public function storeReply(Request $request, string $current_team, ExchangeInviteSend $send): RedirectResponse
    {
        $this->authorizeSend($request, $send);

        Gate::authorize('update', $send->invite);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $send->messages()->create([
            'direction' => 'inbound',
            'channel' => 'manual',
            'body' => $validated['body'],
        ]);

        if (in_array($send->status, [ExchangeInviteSendStatus::Pending, ExchangeInviteSendStatus::Sent, ExchangeInviteSendStatus::Accepted], true)) {
            $send->forceFill([
                'status' => ExchangeInviteSendStatus::Answered,
                'answered_at' => now(),
            ])->save();
        }

        return back();
    }

    /**
     * Mark the send as declined by the partner congregation.
     */
    public function decline(Request $request, string $current_team, ExchangeInviteSend $send): RedirectResponse
    {
        $this->authorizeSend($request, $send);

        Gate::authorize('update', $send->invite);

        $send->forceFill([
            'status' => ExchangeInviteSendStatus::Declined,
            'answered_at' => $send->answered_at ?? now(),
        ])->save();

        return back();
    }

    /**
     * Close the package: confirm the accepted offers, notify our outgoing
     * speakers and send the partner coordinator the summary.
     */
    public function confirm(Request $request, string $current_team, ExchangeInviteSend $send): RedirectResponse
    {
        $this->authorizeSend($request, $send);

        Gate::authorize('update', $send->invite);

        $this->assembler->confirm($send, $request->user());

        return back();
    }

    /**
     * Ensure the send belongs to the current team.
     */
    protected function authorizeSend(Request $request, ExchangeInviteSend $send): void
    {
        abort_unless($send->invite->team_id === $request->user()->currentTeam?->id, 404);
    }

    /**
     * The offers of the send, grouped by nothing but ordered for the page.
     *
     * @return list<array<string, mixed>>
     */
    protected function offersFor(ExchangeInviteSend $send): array
    {
        return $send->offers()
            ->with(['speaker', 'outlines'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ExchangeOffer $offer): array => [
                'id' => $offer->id,
                'direction' => $offer->direction,
                'status' => $offer->status->value,
                'target_date' => $offer->target_date?->toDateString(),
                'chosen_outline_id' => $offer->chosen_outline_id,
                'source_message_id' => $offer->source_message_id,
                'speaker' => [
                    'id' => $offer->speaker->id,
                    'name' => $offer->speaker->name,
                    'phone' => $offer->speaker->phone,
                ],
                'outlines' => $offer->outlines
                    ->map(fn (PublicTalkOutline $outline): array => [
                        'id' => $outline->id,
                        'number' => $outline->number,
                        'title' => $outline->title,
                    ])->all(),
            ])->all();
    }

    /**
     * Active speakers of a congregation with their outlines, for the pickers.
     *
     * @return list<array<string, mixed>>
     */
    protected function speakersOf(string $congregationId): array
    {
        return Speaker::query()
            ->where('congregation_id', $congregationId)
            ->active()
            ->with('outlines')
            ->orderBy('name')
            ->get()
            ->map(fn (Speaker $speaker): array => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'outline_ids' => $speaker->outlines->pluck('id')->all(),
            ])->all();
    }
}
