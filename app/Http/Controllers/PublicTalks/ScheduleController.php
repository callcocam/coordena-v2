<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\PublicTalkOutlineStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTalks\UpdateScheduleSlotRequest;
use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Services\PublicTalks\ScheduleHorizon;
use App\Services\PublicTalks\SpeakerAvailability;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        protected ScheduleHorizon $horizon,
        protected ResponsibleCoordinator $responsible,
        protected SpeakerAvailability $availability,
    ) {}

    /**
     * Show the monthly home-talk schedule, or the setup wizard while the
     * module still misses its home congregation or responsible coordinator.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        Gate::authorize('viewAny', [TalkAssignment::class, $team]);

        $home = $team->homeCongregation;

        if ($home === null || $this->responsible->for($team) === null) {
            return $this->renderSetup($team, $home);
        }

        $this->horizon->ensure($team);

        $month = $this->resolveMonth($request);
        $canManage = Gate::allows('create', [TalkAssignment::class, $team]);

        return Inertia::render('publicTalks/Schedule', [
            'month' => $month->format('Y-m'),
            'months' => $this->horizonMonths(),
            'weeks' => $this->weeksFor($team, $month, $canManage),
            'pendingCount' => $this->pendingCountFor($team, $month),
            'monthComplete' => $this->monthCompleteFor($team, $month),
            'speakers' => $this->speakersFor($home, $month),
            'outlines' => $this->outlineCatalog(),
            'homeCongregation' => [
                'id' => $home->id,
                'name' => $home->name,
                'meeting_weekday' => $home->meeting_weekday,
                'meeting_time' => $home->meeting_time,
            ],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Fill or clear the speaker/outline of a home slot.
     */
    public function update(UpdateScheduleSlotRequest $request, string $current_team, TalkAssignment $assignment): RedirectResponse
    {
        $speakerId = $request->validated('speaker_id');

        if ($speakerId !== null) {
            $assignment->fill([
                'speaker_id' => $speakerId,
                'outline_id' => $request->validated('outline_id'),
                'status' => TalkAssignmentStatus::Scheduled,
                'created_by_id' => $request->user()->id,
            ]);
        } else {
            $assignment->fill([
                'speaker_id' => null,
                'outline_id' => null,
                'status' => TalkAssignmentStatus::Open,
            ]);
        }

        $assignment->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Semana atualizada.')]);

        return back();
    }

    /**
     * The setup wizard, gated by which prerequisite is still missing.
     */
    protected function renderSetup(Team $team, ?Congregation $home): Response
    {
        $owner = $team->owner();

        return Inertia::render('publicTalks/Setup', [
            'step' => $home === null ? 'congregation' : 'coordinator',
            'congregations' => $owner === null ? [] : Congregation::query()
                ->ownedBy($owner->id)
                ->orderBy('name')
                ->get()
                ->map(fn (Congregation $congregation): array => [
                    'id' => $congregation->id,
                    'name' => $congregation->name,
                    'city' => $congregation->city,
                ])
                ->all(),
            'canManage' => Gate::allows('create', [TalkAssignment::class, $team]),
        ]);
    }

    /**
     * The month requested via `?month=YYYY-MM`, clamped to the horizon.
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
            ->map(fn (int $offset): array => ['value' => $start->copy()->addMonths($offset)->format('Y-m')])
            ->all();
    }

    /**
     * The weekly slots of the given month, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    protected function weeksFor(Team $team, Carbon $month, bool $canManage): array
    {
        return TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->with(['speaker:id,name,phone', 'outline:id,number,title', 'counterpartCongregation:id,name', 'latestNotification'])
            ->orderBy('date')
            ->get()
            ->each(fn (TalkAssignment $assignment) => $assignment->setRelation('team', $team))
            ->map(fn (TalkAssignment $assignment): array => [
                'id' => $assignment->id,
                'date' => $assignment->date->toDateString(),
                'week_start' => $assignment->week_start->toDateString(),
                'type' => $assignment->type->value,
                'status' => $assignment->status->value,
                'speaker' => $assignment->speaker === null ? null : [
                    'id' => $assignment->speaker->id,
                    'name' => $assignment->speaker->name,
                    'phone' => $assignment->speaker->phone,
                ],
                'outline' => $assignment->outline === null ? null : [
                    'id' => $assignment->outline->id,
                    'number' => $assignment->outline->number,
                    'title' => $assignment->outline->title,
                ],
                'counterpart' => $assignment->counterpartCongregation?->name,
                'editable' => $canManage && $assignment->type === TalkAssignmentType::Home,
                'notifiable' => $this->notifiable($assignment),
                'notification' => $assignment->latestNotification === null ? null : [
                    'kind' => $assignment->latestNotification->kind->value,
                    'status' => $assignment->latestNotification->status->value,
                    'sent_at' => $assignment->latestNotification->sent_at?->toDateString(),
                ],
            ])
            ->all();
    }

    /**
     * Whether the schedule can offer the WhatsApp notify action for the slot:
     * speaker filled (any direction — the visitor is notified by us too),
     * with a normalizable phone, and the team's WhatsApp API ready.
     */
    protected function notifiable(TalkAssignment $assignment): bool
    {
        return $assignment->speaker_id !== null
            && $assignment->outline_id !== null
            && Phone::normalize($assignment->speaker?->phone) !== null
            && $assignment->team->canSendWhatsappApi()
            && Gate::allows('notify', $assignment);
    }

    /**
     * Whether every week of the month has been filled (no open slots left).
     */
    protected function monthCompleteFor(Team $team, Carbon $month): bool
    {
        return TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotIn('status', [TalkAssignmentStatus::Open])
            ->exists()
            && ! TalkAssignment::query()
                ->where('team_id', $team->id)
                ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->where('status', TalkAssignmentStatus::Open)
                ->exists();
    }

    /**
     * How many slots of the month are filled but not yet confirmed.
     */
    protected function pendingCountFor(Team $team, Carbon $month): int
    {
        return TalkAssignment::query()
            ->where('team_id', $team->id)
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereIn('status', [TalkAssignmentStatus::Scheduled, TalkAssignmentStatus::Notified])
            ->count();
    }

    /**
     * The active home speakers with their outlines and month availability.
     *
     * @return list<array<string, mixed>>
     */
    protected function speakersFor(Congregation $home, Carbon $month): array
    {
        return $home->speakers()
            ->active()
            ->with('outlines:id')
            ->orderBy('name')
            ->get()
            ->map(fn (Speaker $speaker): array => [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'role' => $speaker->role->value,
                'phone' => $speaker->phone,
                'outline_ids' => $speaker->outlines->modelKeys(),
                'available' => $this->availability->canGoOut($speaker, $month),
            ])
            ->all();
    }

    /**
     * The active outline catalog, ordered by number.
     *
     * @return list<array<string, mixed>>
     */
    protected function outlineCatalog(): array
    {
        return PublicTalkOutline::query()
            ->where('status', PublicTalkOutlineStatus::Active)
            ->orderBy('number')
            ->get(['id', 'number', 'title', 'theme', 'reference_url'])
            ->map(fn (PublicTalkOutline $outline): array => [
                'id' => $outline->id,
                'number' => $outline->number,
                'title' => $outline->title,
                'theme' => $outline->theme,
                'reference_url' => $outline->reference_url,
            ])
            ->all();
    }
}
