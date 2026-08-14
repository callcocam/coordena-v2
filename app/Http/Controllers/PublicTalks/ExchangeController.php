<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\ExchangeInviteSendStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\StoreExchangeSendRequest;
use App\Jobs\SendExchangeInvite;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ExchangeInviteManager;
use App\Services\PublicTalks\ExchangeRoundRobin;
use App\Services\PublicTalks\InviteComposer;
use App\Services\PublicTalks\ScheduleHorizon;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeController extends Controller
{
    public function __construct(
        protected ExchangeInviteManager $manager,
        protected ExchangeRoundRobin $roundRobin,
        protected InviteComposer $composer,
        protected ScheduleHorizon $horizon,
    ) {}

    /**
     * Show the monthly exchange invite: open weeks, round-robin suggestion,
     * composed text and the sends already made.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        Gate::authorize('viewAny', [ExchangeInvite::class, $team]);

        $this->horizon->ensure($team);

        $month = $this->resolveMonth($request);
        $invite = $this->manager->forMonth($team, $month, $request->user());

        $candidates = $this->roundRobin->candidatesFor($invite);
        $selected = $this->selectedCongregation($request, $candidates->pluck('id')->all());

        return Inertia::render('publicTalks/Exchange', [
            'month' => $month->format('Y-m'),
            'months' => $this->horizonMonths(),
            'invite' => [
                'id' => $invite->id,
                'status' => $invite->status->value,
            ],
            'openWeeks' => $this->manager->openWeeks($invite)
                ->map(fn (TalkAssignment $week): array => [
                    'id' => $week->id,
                    'date' => $week->date->toDateString(),
                ])->all(),
            'suggestionId' => $this->roundRobin->nextFor($invite)?->id,
            'candidates' => $candidates
                ->map(fn (Congregation $congregation): array => [
                    'id' => $congregation->id,
                    'name' => $congregation->name,
                    'city' => $congregation->city,
                ])->all(),
            'selectedId' => $selected?->id,
            'composeText' => $selected !== null ? $this->composer->compose($invite, $selected) : null,
            'sends' => $this->sendsFor($invite),
            'canSend' => Gate::allows('send', $invite),
            'whatsappEnabled' => $team->canSendWhatsappApi(),
            'selectedHasWhatsapp' => $selected !== null && Phone::normalize($selected->contact_phone) !== null,
        ]);
    }

    /**
     * Register a send of the invite to a partner congregation, either manual
     * (copy/paste, marked sent right away) or via WhatsApp Cloud (queued as
     * pending and delivered by {@see SendExchangeInvite}).
     */
    public function storeSend(StoreExchangeSendRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $month = Carbon::createFromFormat('Y-m-d', $request->string('month')->value().'-01')->startOfMonth();
        $invite = $this->manager->forMonth($team, $month, $request->user());

        Gate::authorize('send', $invite);

        $congregation = Congregation::query()->findOrFail($request->string('congregation_id')->value());
        $channel = $request->string('channel')->value();

        if ($channel === 'whatsapp') {
            $this->ensureCanSendWhatsapp($team, $congregation);
        }

        $send = $invite->sends()->create([
            'congregation_id' => $congregation->id,
            'channel' => $channel,
            'portal_token' => Str::random(48),
            'status' => $channel === 'whatsapp'
                ? ExchangeInviteSendStatus::Pending
                : ExchangeInviteSendStatus::Sent,
            'sent_at' => $channel === 'whatsapp' ? null : now(),
            'sent_by_id' => $request->user()->id,
        ]);

        if ($channel === 'whatsapp') {
            SendExchangeInvite::dispatch($send);
        } else {
            $send->messages()->create([
                'direction' => 'outbound',
                'channel' => 'manual',
                'body' => $this->composer->compose($invite, $congregation),
            ]);
        }

        return back();
    }

    /**
     * Block the WhatsApp channel when the team cannot send via the Cloud API
     * or the congregation has no valid phone number.
     */
    protected function ensureCanSendWhatsapp(Team $team, Congregation $congregation): void
    {
        if (! $team->canSendWhatsappApi()) {
            throw ValidationException::withMessages([
                'channel' => __('O canal WhatsApp não está pronto neste time: ative a API, aceite os termos e conecte um número.'),
            ]);
        }

        if (Phone::normalize($congregation->contact_phone) === null) {
            throw ValidationException::withMessages([
                'congregation_id' => __('A congregação :name não tem um telefone WhatsApp válido cadastrado.', ['name' => $congregation->name]),
            ]);
        }
    }

    /**
     * The candidate chosen via query string, defaulting to none.
     *
     * @param  list<string>  $candidateIds
     */
    protected function selectedCongregation(Request $request, array $candidateIds): ?Congregation
    {
        $id = $request->query('congregation');

        if (! is_string($id) || ! in_array($id, $candidateIds, true)) {
            return null;
        }

        return Congregation::query()->find($id);
    }

    /**
     * The sends of the invite, newest first, for the page.
     *
     * @return list<array<string, mixed>>
     */
    protected function sendsFor(ExchangeInvite $invite): array
    {
        return $invite->sends()
            ->with('congregation')
            ->withCount('offers')
            ->latest()
            ->get()
            ->map(fn (ExchangeInviteSend $send): array => [
                'id' => $send->id,
                'status' => $send->status->value,
                'sent_at' => $send->sent_at?->toDateTimeString(),
                'answered_at' => $send->answered_at?->toDateTimeString(),
                'offers_count' => $send->offers_count,
                'congregation' => [
                    'id' => $send->congregation->id,
                    'name' => $send->congregation->name,
                ],
            ])->all();
    }

    /**
     * The month asked via query string, clamped to the schedule horizon.
     */
    protected function resolveMonth(Request $request): Carbon
    {
        $current = Carbon::today()->startOfMonth();
        $raw = $request->query('month');

        if (! is_string($raw) || preg_match('/^\d{4}-\d{2}$/', $raw) !== 1) {
            return $current;
        }

        $month = Carbon::createFromFormat('Y-m-d', $raw.'-01')->startOfMonth();
        $last = $current->copy()->addMonths(ScheduleHorizon::MONTHS_AHEAD - 1);

        return $month->between($current, $last) ? $month : $current;
    }

    /**
     * The months covered by the schedule horizon.
     *
     * @return list<array{value: string}>
     */
    protected function horizonMonths(): array
    {
        $start = Carbon::today()->startOfMonth();

        return collect(range(0, ScheduleHorizon::MONTHS_AHEAD - 1))
            ->map(fn (int $offset): array => [
                'value' => $start->copy()->addMonths($offset)->format('Y-m'),
            ])->all();
    }
}
