<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\PublicTalkOutlineStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SubmitExchangePortalRequest;
use App\Models\ExchangeInviteSend;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\ExchangeInviteManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExchangePortalController extends Controller
{
    public function __construct(protected ExchangeInviteManager $manager) {}

    /**
     * Public portal for the invited congregation, keyed by portal token.
     */
    public function show(string $token): Response
    {
        $send = $this->findSend($token);

        $invite = $send->invite;
        $home = $invite->team->homeCongregation;

        return Inertia::render('publicTalks/ExchangePortal', [
            'token' => $token,
            'month' => $invite->month->format('Y-m'),
            'homeCongregation' => $home?->name,
            'invitedCongregation' => $send->congregation->name,
            'closed' => $this->isClosed($send),
            'openWeeks' => $this->manager->openWeeks($invite)
                ->map(fn (TalkAssignment $week): array => [
                    'date' => $week->date->toDateString(),
                ])->all(),
            'homeSpeakers' => $home === null ? [] : Speaker::query()
                ->where('congregation_id', $home->id)
                ->active()
                ->with('outlines')
                ->orderBy('name')
                ->get()
                ->map(fn (Speaker $speaker): array => [
                    'name' => $speaker->name,
                    'outlines' => $speaker->outlines
                        ->map(fn (PublicTalkOutline $outline): string => sprintf('%d — %s', $outline->number, $outline->title))
                        ->all(),
                ])->all(),
        ]);
    }

    /**
     * Store the invited congregation's structured reply: one inbound message
     * plus draft offers ready for the coordinator's review. Never confirms.
     */
    public function store(SubmitExchangePortalRequest $request, string $token): RedirectResponse
    {
        $send = $this->findSend($token);

        abort_if($this->isClosed($send), 410);

        $validated = $request->validated();

        DB::transaction(function () use ($send, $validated): void {
            $message = $send->messages()->create([
                'direction' => 'inbound',
                'channel' => 'portal',
                'body' => $this->summarize($validated['offers']),
            ]);

            foreach ($validated['offers'] as $row) {
                $speaker = Speaker::query()->firstOrCreate(
                    [
                        'congregation_id' => $send->congregation_id,
                        'name' => trim($row['speaker_name']),
                    ],
                    [
                        /** Materialized as inactive until an offer is confirmed (invariant: talk_assignment only with a real, vetted speaker). */
                        'is_active' => false,
                        'phone' => $row['phone'] ?? null,
                    ],
                );

                $offer = $send->offers()->create([
                    'direction' => 'incoming',
                    'speaker_id' => $speaker->id,
                    'target_date' => $row['date'],
                    'status' => ExchangeOfferStatus::Draft,
                    'source_message_id' => $message->id,
                ]);

                if (isset($row['outline_number'])) {
                    $outline = PublicTalkOutline::query()
                        ->where('number', $row['outline_number'])
                        ->where('status', PublicTalkOutlineStatus::Active)
                        ->first();

                    if ($outline !== null) {
                        $offer->outlines()->sync([$outline->id]);
                    }
                }
            }

            if (in_array($send->status, [ExchangeInviteSendStatus::Pending, ExchangeInviteSendStatus::Sent], true)) {
                $send->forceFill([
                    'status' => ExchangeInviteSendStatus::Answered,
                    'answered_at' => now(),
                ])->save();
            }
        });

        return back()->with('portal_submitted', true);
    }

    /**
     * Resolve a send by its portal token or 404.
     */
    protected function findSend(string $token): ExchangeInviteSend
    {
        abort_if($token === '', 404);

        return ExchangeInviteSend::query()
            ->where('portal_token', $token)
            ->with(['invite.team', 'congregation'])
            ->firstOrFail();
    }

    /**
     * Whether the send no longer accepts submissions.
     */
    protected function isClosed(ExchangeInviteSend $send): bool
    {
        return in_array($send->status, [
            ExchangeInviteSendStatus::Declined,
            ExchangeInviteSendStatus::Expired,
        ], true);
    }

    /**
     * Human-readable summary of the portal submission, stored as the inbound message body.
     *
     * @param  list<array<string, mixed>>  $offers
     */
    protected function summarize(array $offers): string
    {
        $lines = [__('app.public_talks.exchange.portal.summary_heading')];

        foreach ($offers as $row) {
            $lines[] = sprintf(
                '- %s — %s%s%s',
                Carbon::parse($row['date'])->translatedFormat('d/M'),
                trim($row['speaker_name']),
                isset($row['outline_number']) ? sprintf(' (tema %d)', $row['outline_number']) : '',
                filled($row['phone'] ?? null) ? sprintf(' — %s', $row['phone']) : '',
            );
        }

        return implode("\n", $lines);
    }
}
