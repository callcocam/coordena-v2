<?php

namespace App\Http\Controllers\PublicTalks;

use App\Enums\TalkAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\CongregationIntro;
use App\Models\TalkAssignment;
use App\Services\PublicTalks\ResponsibleCoordinator;
use Inertia\Inertia;
use Inertia\Response;

class IntroPortalController extends Controller
{
    public function __construct(protected ResponsibleCoordinator $responsible) {}

    /**
     * Public schedule page linked from the intro message, keyed by the
     * intro's portal token. Read-only: the invited congregation only sees who
     * we are and our upcoming home talks.
     */
    public function show(string $token): Response
    {
        $intro = CongregationIntro::query()
            ->where('portal_token', $token)
            ->with('team.homeCongregation')
            ->firstOrFail();

        $team = $intro->team;
        $home = $team->homeCongregation;
        $coordinator = $this->responsible->for($team);

        return Inertia::render('publicTalks/IntroPortal', [
            'homeCongregation' => $home?->name ?? $team->name,
            'city' => $home?->city,
            'meetingWeekday' => $home?->meeting_weekday,
            'meetingTime' => $home?->meeting_time !== null
                ? substr($home->meeting_time, 0, 5)
                : null,
            'coordinator' => $coordinator === null ? null : [
                'name' => $coordinator->name,
                'phone' => $coordinator->phone,
            ],
            'weeks' => $this->upcomingWeeks($team->id),
        ]);
    }

    /**
     * The next confirmed or scheduled home talks (8 weeks ahead).
     *
     * @return list<array<string, mixed>>
     */
    protected function upcomingWeeks(string $teamId): array
    {
        return TalkAssignment::query()
            ->where('team_id', $teamId)
            ->whereDate('date', '>=', now()->startOfWeek())
            ->whereIn('status', [
                TalkAssignmentStatus::Scheduled,
                TalkAssignmentStatus::Notified,
                TalkAssignmentStatus::Confirmed,
            ])
            ->with(['speaker:id,name', 'outline:id,number,title'])
            ->orderBy('date')
            ->limit(8)
            ->get()
            ->map(fn (TalkAssignment $assignment): array => [
                'id' => $assignment->id,
                'date' => $assignment->date->toDateString(),
                'speaker' => $assignment->speaker?->name,
                'outline' => $assignment->outline === null
                    ? null
                    : sprintf('%d — %s', $assignment->outline->number, $assignment->outline->title),
            ])
            ->all();
    }
}
