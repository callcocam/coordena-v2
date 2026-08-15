<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Enums\PublicTalkOutlineStatus;
use App\Enums\TalkAssignmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\SubmitExchangePortalRequest;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\ExchangeInviteManager;
use App\Services\PublicTalks\SpeakerAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExchangePortalController extends Controller
{
    public function __construct(
        protected ExchangeInviteManager $manager,
        protected SpeakerAvailability $availability,
    ) {}

    /**
     * Public portal for the invited congregation, keyed by portal token.
     *
     * Semana é a unidade central: `openWeeks` são as NOSSAS semanas abertas
     * (eles enviam orador) e `monthWeeks` as semanas do mês em que ELES podem
     * receber um orador nosso.
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
            'meetingTime' => $home?->meeting_time !== null
                ? substr($home->meeting_time, 0, 5)
                : null,
            'invitedCongregation' => $send->congregation->name,
            'closed' => $this->isClosed($send),
            'openWeeks' => $this->manager->openWeeks($invite)
                ->map(fn (TalkAssignment $week): array => [
                    'date' => $week->date->toDateString(),
                ])->all(),
            'monthWeeks' => array_map(fn (string $week): array => ['week' => $week], $this->outgoingWeeks($invite)),
            'homeSpeakers' => $this->exposedSpeakers($send)
                ->map(fn (Speaker $speaker): array => [
                    'id' => $speaker->id,
                    'name' => $speaker->name,
                    'outlines' => $speaker->outlines
                        ->map(fn (PublicTalkOutline $outline): array => [
                            'id' => $outline->id,
                            'number' => $outline->number,
                            'title' => $outline->title,
                        ])->values()->all(),
                ])->values()->all(),
            'partnerSpeakers' => $this->partnerSpeakers($send)
                ->map(fn (Speaker $speaker): array => [
                    'id' => $speaker->id,
                    'name' => $speaker->name,
                    'phone' => $speaker->phone,
                    'outline_ids' => $speaker->outlines->pluck('id')->values()->all(),
                ])->values()->all(),
            'outlineCatalog' => $this->outlineCatalog()
                ->map(fn (PublicTalkOutline $outline): array => [
                    'id' => $outline->id,
                    'number' => $outline->number,
                    'title' => $outline->title,
                ])->values()->all(),
            'arrangement' => $this->arrangement($send),
            'recentOutlines' => $this->recentOutlines($send),
            'helpUrl' => route('help.public-talks'),
            'expiresAt' => $this->expiresAt($send),
        ]);
    }

    /**
     * Store the invited congregation's structured reply: one inbound message
     * plus draft offers (both directions) ready for the coordinator's review.
     * Never confirms — aceite/recusa/tema dos incoming é papel da mesa.
     */
    public function store(SubmitExchangePortalRequest $request, string $token): RedirectResponse
    {
        $send = $this->findSend($token);

        abort_if($this->isClosed($send), 410);

        $validated = $request->validated();
        $incoming = $validated['incoming'] ?? [];
        $outgoing = $validated['outgoing'] ?? [];

        $exposed = $this->exposedSpeakers($send);
        $partnerSpeakers = $this->partnerSpeakers($send)->keyBy('id');
        $catalog = $this->outlineCatalog()->keyBy('id');

        $this->validateAgainstInvite($send, $incoming, $outgoing, $exposed, $partnerSpeakers, $catalog);

        DB::transaction(function () use ($send, $incoming, $outgoing, $exposed, $partnerSpeakers, $catalog): void {
            $message = $send->messages()->create([
                'direction' => 'inbound',
                'channel' => 'portal',
                'body' => $this->summarize($incoming, $outgoing, $exposed, $partnerSpeakers, $catalog),
            ]);

            foreach ($incoming as $row) {
                $speaker = filled($row['speaker_id'] ?? null)
                    ? $partnerSpeakers->get($row['speaker_id'])
                    : Speaker::query()->firstOrCreate(
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

                if (filled($row['phone'] ?? null) && $speaker->phone !== $row['phone']) {
                    $speaker->forceFill(['phone' => $row['phone']])->save();
                }

                $offer = $send->offers()->create([
                    'direction' => 'incoming',
                    'speaker_id' => $speaker->id,
                    'target_date' => $row['week'],
                    'status' => ExchangeOfferStatus::Draft,
                    'source_message_id' => $message->id,
                ]);

                /** Regra do tema: incoming carrega a LISTA de esboços do orador (ids do catálogo, já validados); o nosso coordenador escolhe na mesa (melhoria 5). */
                $offer->outlines()->sync($row['outline_ids'] ?? []);
            }

            foreach ($outgoing as $row) {
                $offer = $send->offers()->create([
                    'direction' => 'outgoing',
                    'speaker_id' => $row['speaker_id'],
                    'target_date' => $row['week'],
                    'status' => ExchangeOfferStatus::Draft,
                    'source_message_id' => $message->id,
                ]);

                /** Regra do tema: quem recebe escolhe — outgoing grava o único esboço escolhido por ELES. */
                $offer->outlines()->sync([$row['outline_id']]);
            }

            if (in_array($send->status, [ExchangeInviteSendStatus::Pending, ExchangeInviteSendStatus::Sent, ExchangeInviteSendStatus::Accepted], true)) {
                $send->forceFill([
                    'status' => ExchangeInviteSendStatus::Answered,
                    'answered_at' => now(),
                ])->save();
            }
        });

        return back()->with('portal_submitted', true);
    }

    /**
     * Cross-field validation that depends on the invite: weeks must exist on
     * our side (incoming) or inside the invite month (outgoing), and outgoing
     * picks must reference an exposed speaker and an outline he delivers.
     *
     * Incoming rows referencing a registered partner speaker must point to a
     * speaker of the invited congregation, and outline ids must exist in the
     * active catalog.
     *
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<array<string, mixed>>  $outgoing
     * @param  Collection<int, Speaker>  $exposed
     * @param  Collection<string, Speaker>  $partnerSpeakers
     * @param  Collection<string, PublicTalkOutline>  $catalog
     */
    protected function validateAgainstInvite(ExchangeInviteSend $send, array $incoming, array $outgoing, Collection $exposed, Collection $partnerSpeakers, Collection $catalog): void
    {
        $invite = $send->invite;
        $openDates = $this->manager->openWeeks($invite)
            ->map(fn (TalkAssignment $week): string => $week->date->toDateString())
            ->all();
        $outgoingWeeks = $this->outgoingWeeks($invite);
        $speakersById = $exposed->keyBy('id');

        $errors = [];
        $seenIncomingSpeakers = [];
        $seenIncomingWeeks = [];

        foreach ($incoming as $index => $row) {
            $incomingWeek = Carbon::parse($row['week'])->toDateString();

            if (! in_array($incomingWeek, $openDates, true)) {
                $errors["incoming.{$index}.week"] = __('app.public_talks.exchange.portal.errors.week_not_open');
            }

            if (isset($seenIncomingWeeks[$incomingWeek])) {
                $errors["incoming.{$index}.week"] = __('app.public_talks.exchange.portal.errors.week_repeated');
            }

            $seenIncomingWeeks[$incomingWeek] = true;

            if (filled($row['speaker_id'] ?? null) && ! $partnerSpeakers->has($row['speaker_id'])) {
                $errors["incoming.{$index}.speaker_id"] = __('app.public_talks.exchange.portal.errors.unknown_speaker');
            }

            $speakerKey = filled($row['speaker_id'] ?? null)
                ? 'id:'.$row['speaker_id']
                : 'name:'.mb_strtolower(trim((string) ($row['speaker_name'] ?? '')));

            if ($speakerKey !== 'name:') {
                if (isset($seenIncomingSpeakers[$speakerKey])) {
                    $field = filled($row['speaker_id'] ?? null) ? 'speaker_id' : 'speaker_name';
                    $errors["incoming.{$index}.{$field}"] = __('app.public_talks.exchange.portal.errors.speaker_repeated');
                }

                $seenIncomingSpeakers[$speakerKey] = true;
            }

            foreach ($row['outline_ids'] ?? [] as $outlineId) {
                if (! $catalog->has($outlineId)) {
                    $errors["incoming.{$index}.outline_ids"] = __('app.public_talks.exchange.portal.errors.outline_unknown');

                    break;
                }
            }
        }

        $seenOutgoingSpeakers = [];
        $seenOutgoingWeeks = [];

        foreach ($outgoing as $index => $row) {
            $outgoingWeek = Carbon::parse($row['week'])->toDateString();

            if (! in_array($outgoingWeek, $outgoingWeeks, true)) {
                $errors["outgoing.{$index}.week"] = __('app.public_talks.exchange.portal.errors.week_outside_month');
            }

            if (isset($seenOutgoingWeeks[$outgoingWeek])) {
                $errors["outgoing.{$index}.week"] = __('app.public_talks.exchange.portal.errors.week_repeated');
            }

            $seenOutgoingWeeks[$outgoingWeek] = true;

            if (isset($seenOutgoingSpeakers[$row['speaker_id']])) {
                $errors["outgoing.{$index}.speaker_id"] = __('app.public_talks.exchange.portal.errors.speaker_repeated');
            }

            $seenOutgoingSpeakers[$row['speaker_id']] = true;

            /** @var Speaker|null $speaker */
            $speaker = $speakersById->get($row['speaker_id']);

            if ($speaker === null) {
                $errors["outgoing.{$index}.speaker_id"] = __('app.public_talks.exchange.portal.errors.unknown_speaker');

                continue;
            }

            if (! $speaker->outlines->contains('id', $row['outline_id'])) {
                $errors["outgoing.{$index}.outline_id"] = __('app.public_talks.exchange.portal.errors.outline_not_offered');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Our speakers visible on this portal, with outlines loaded.
     *
     * Melhoria 3 exposure gate: WhatsApp invites only reveal our speakers
     * after the congregation tapped "accept" (never in the cold template).
     * Manual sends keep the old behavior — the coordinator composed the text
     * and already chose what to share.
     *
     * @return Collection<int, Speaker>
     */
    protected function exposedSpeakers(ExchangeInviteSend $send): Collection
    {
        $home = $send->invite->team->homeCongregation;

        if ($home === null || ! $this->exposesSpeakers($send)) {
            return new Collection;
        }

        return $this->availability->availableFor($home, $send->invite->month)->toBase();
    }

    /**
     * Whether the send reveals our speakers on the portal.
     */
    protected function exposesSpeakers(ExchangeInviteSend $send): bool
    {
        return $send->channel !== 'whatsapp' || $send->accepted_at !== null;
    }

    /**
     * Registered speakers of the INVITED congregation (melhoria 7): lets the
     * portal pre-fill the combobox with speakers we already know, instead of
     * always retyping free text. Includes inactive drafts — they were created
     * by a previous portal submission and are exactly who tends to reappear.
     *
     * @return Collection<int, Speaker>
     */
    protected function partnerSpeakers(ExchangeInviteSend $send): Collection
    {
        return Speaker::query()
            ->where('congregation_id', $send->congregation_id)
            ->with('outlines:id,number,title')
            ->orderBy('name')
            ->get()
            ->toBase();
    }

    /**
     * Active outline catalog for the tags-input on the portal (melhoria 7).
     *
     * @return Collection<int, PublicTalkOutline>
     */
    protected function outlineCatalog(): Collection
    {
        return PublicTalkOutline::query()
            ->where('status', PublicTalkOutlineStatus::Active)
            ->orderBy('number')
            ->get(['id', 'number', 'title'])
            ->toBase();
    }

    /**
     * ISO weeks (Mondays) of the invite month still ahead, where the invited
     * congregation can receive one of our speakers. Sem dia/hora: a data
     * concreta da reunião deles só entra na confirmação (melhoria 5).
     *
     * @return list<string>
     */
    protected function outgoingWeeks(ExchangeInvite $invite): array
    {
        $monthStart = $invite->month->copy()->startOfMonth();
        $monthEnd = $invite->month->copy()->endOfMonth();

        $monday = $monthStart->copy()->startOfWeek(Carbon::MONDAY);

        if ($monday->lt($monthStart)) {
            $monday = $monday->addWeek();
        }

        $weeks = [];

        while ($monday->lte($monthEnd)) {
            if (! $monday->copy()->endOfWeek(Carbon::SUNDAY)->isPast()) {
                $weeks[] = $monday->toDateString();
            }

            $monday = $monday->copy()->addWeek();
        }

        return $weeks;
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
     * Offers of this send, from the invited congregation's point of view:
     * `outgoing` = eles enviam um orador, `incoming` = eles recebem.
     *
     * @return list<array<string, mixed>>
     */
    protected function arrangement(ExchangeInviteSend $send): array
    {
        return $send->offers()
            ->with(['speaker', 'chosenOutline', 'outlines'])
            ->orderBy('target_date')
            ->get()
            ->map(fn (ExchangeOffer $offer): array => [
                'week' => $offer->target_date?->toDateString(),
                'direction' => $offer->direction === 'incoming' ? 'outgoing' : 'incoming',
                'speaker_name' => $offer->speaker->name,
                'outline' => $this->offerOutline($offer),
                'status' => $offer->status->value,
            ])->values()->all();
    }

    /**
     * @return array{number: int, title: string}|null
     */
    protected function offerOutline(ExchangeOffer $offer): ?array
    {
        $outline = $offer->chosenOutline
            ?? ($offer->outlines->count() === 1 ? $offer->outlines->first() : null);

        return $outline !== null
            ? ['number' => $outline->number, 'title' => $outline->title]
            : null;
    }

    /**
     * Outlines the invited congregation already received from us recently
     * (últimos 6 meses), para eles evitarem repetição ao escolher temas.
     *
     * @return list<array<string, mixed>>
     */
    protected function recentOutlines(ExchangeInviteSend $send): array
    {
        $since = now()->subMonths(6)->startOfDay();
        $teamId = $send->invite->team_id;

        $fromAssignments = TalkAssignment::query()
            ->where('team_id', $teamId)
            ->where('type', TalkAssignmentType::Outgoing)
            ->where('counterpart_congregation_id', $send->congregation_id)
            ->whereNotNull('outline_id')
            ->where('date', '>=', $since)
            ->with(['outline', 'speaker'])
            ->get()
            ->map(fn (TalkAssignment $assignment): array => [
                'date' => $assignment->date->toDateString(),
                'outline' => [
                    'number' => $assignment->outline->number,
                    'title' => $assignment->outline->title,
                ],
                'speaker_name' => $assignment->speaker?->name,
            ]);

        $fromOffers = ExchangeOffer::query()
            ->where('direction', 'outgoing')
            ->where('status', ExchangeOfferStatus::Confirmed)
            ->where('target_date', '>=', $since)
            ->whereHas('inviteSend', fn ($query) => $query
                ->where('congregation_id', $send->congregation_id)
                ->where('id', '!=', $send->id)
                ->whereHas('invite', fn ($inviteQuery) => $inviteQuery->where('team_id', $teamId)))
            ->with(['speaker', 'chosenOutline', 'outlines'])
            ->get()
            ->map(fn (ExchangeOffer $offer): ?array => $this->offerOutline($offer) !== null
                ? [
                    'date' => $offer->target_date?->toDateString(),
                    'outline' => $this->offerOutline($offer),
                    'speaker_name' => $offer->speaker->name,
                ]
                : null)
            ->filter();

        return $fromAssignments->concat($fromOffers)
            ->unique(fn (array $item): string => $item['date'].'|'.$item['outline']['number'])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * Date after which a `sent` send expires; null when not applicable.
     */
    protected function expiresAt(ExchangeInviteSend $send): ?string
    {
        if ($send->status !== ExchangeInviteSendStatus::Sent) {
            return null;
        }

        return $send->created_at
            ?->addDays((int) config('public_talks.exchange.expire_after_days'))
            ->toDateString();
    }

    /**
     * Human-readable summary of the portal submission, stored as the inbound
     * message body (também o que o coordenador vê no histórico).
     *
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<array<string, mixed>>  $outgoing
     * @param  Collection<int, Speaker>  $exposed
     */
    protected function summarize(array $incoming, array $outgoing, Collection $exposed, Collection $partnerSpeakers, Collection $catalog): string
    {
        $speakersById = $exposed->keyBy('id');
        $lines = [__('app.public_talks.exchange.portal.summary_heading')];

        if ($incoming !== []) {
            $lines[] = __('app.public_talks.exchange.portal.summary_incoming_heading');

            foreach ($incoming as $row) {
                $name = filled($row['speaker_id'] ?? null)
                    ? ($partnerSpeakers->get($row['speaker_id'])?->name ?? $row['speaker_id'])
                    : trim($row['speaker_name']);

                $numbers = collect($row['outline_ids'] ?? [])
                    ->map(fn (string $id): ?int => $catalog->get($id)?->number)
                    ->filter()
                    ->values()
                    ->all();

                $lines[] = sprintf(
                    '- %s — %s%s%s',
                    Carbon::parse($row['week'])->translatedFormat('d/M'),
                    $name,
                    $numbers !== [] ? sprintf(' (temas %s)', implode(', ', $numbers)) : '',
                    filled($row['phone'] ?? null) ? sprintf(' — %s', $row['phone']) : '',
                );
            }
        }

        if ($outgoing !== []) {
            $lines[] = __('app.public_talks.exchange.portal.summary_outgoing_heading');

            foreach ($outgoing as $row) {
                /** @var Speaker|null $speaker */
                $speaker = $speakersById->get($row['speaker_id']);
                $outline = $speaker?->outlines->firstWhere('id', $row['outline_id']);

                $lines[] = sprintf(
                    '- %s — %s%s',
                    Carbon::parse($row['week'])->translatedFormat('d/M'),
                    $speaker?->name ?? $row['speaker_id'],
                    $outline !== null ? sprintf(' (tema %d)', $outline->number) : '',
                );
            }
        }

        return implode("\n", $lines);
    }
}
