<?php

use App\Enums\SpeakerNotificationKind;
use App\Enums\TalkAssignmentStatus;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\CoordinatorAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

function reminderTeam(): Team
{
    $team = Team::factory()->create([
        'home_congregation_id' => Congregation::factory()->create()->id,
    ]);

    Coordinator::factory()->responsible()->create(['team_id' => $team->id]);

    return $team;
}

function reminderAssignment(Team $team, Carbon $date): TalkAssignment
{
    return TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $date->toDateString(),
        'speaker_id' => Speaker::factory()->create([
            'congregation_id' => $team->home_congregation_id,
        ])->id,
        'status' => TalkAssignmentStatus::Scheduled,
    ]);
}

test('queues the d-3 reminder once per home assignment', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = Carbon::today()->addDays((int) config('public_talks.reminders.speaker_days_before'));
    $assignment = reminderAssignment($team, $date);

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);
});

test('skips assignments outside the reminder date or without notifiable speaker', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = Carbon::today()->addDays((int) config('public_talks.reminders.speaker_days_before'));

    reminderAssignment($team, $date->copy()->addWeek());

    $withoutPhone = reminderAssignment($team, $date);
    $withoutPhone->speaker->forceFill(['phone' => null])->save();

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('alerts the coordinator about unconfirmed talks on d-1, once per day', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = Carbon::today()->addDays((int) config('public_talks.reminders.pending_days_before'));
    reminderAssignment($team, $date);

    $this->mock(CoordinatorAlert::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn (Team $alerted, string $summary): bool => $alerted->is($team)
            && str_contains($summary, 'sem confirmação'));

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();
    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();
});

test('confirmed talks do not trigger the d-1 alert', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = Carbon::today()->addDays((int) config('public_talks.reminders.pending_days_before'));
    reminderAssignment($team, $date)->forceFill(['status' => TalkAssignmentStatus::Confirmed])->save();

    $this->mock(CoordinatorAlert::class)->shouldNotReceive('send');

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();
});

test('dry-run neither queues nor creates notifications', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = Carbon::today()->addDays((int) config('public_talks.reminders.speaker_days_before'));
    $assignment = reminderAssignment($team, $date);

    $this->artisan('public-talks:send-speaker-reminders', ['--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});
