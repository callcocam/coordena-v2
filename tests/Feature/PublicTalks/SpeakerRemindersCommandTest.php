<?php

use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\CoordinatorAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
 * Congela o relógio numa sexta-feira: com D-1 caindo no sábado e D-3 na
 * segunda seguinte, os lembretes ficam em semanas diferentes e os cenários
 * com dois assignments do mesmo time não estouram a unique team_id+week_start
 * (rodar num domingo colocava D-1 e D-3 na mesma semana).
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('next friday')->setTime(8, 0));
});

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
        'outline_id' => PublicTalkOutline::factory()->create()->id,
        'status' => TalkAssignmentStatus::Scheduled,
    ]);
}

function firstReminderDate(): Carbon
{
    return Carbon::today()->addDays((int) config('public_talks.reminders.speaker_days_before'));
}

function secondReminderDate(): Carbon
{
    return Carbon::today()->addDays((int) config('public_talks.reminders.speaker_second_days_before'));
}

test('queues the d-3 reminder once per home assignment', function () {
    Queue::fake();

    $team = reminderTeam();
    $assignment = reminderAssignment($team, firstReminderDate());

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);
});

test('queues reminders for outgoing and incoming assignments too', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = firstReminderDate();

    $outgoing = reminderAssignment($team, $date);
    $outgoing->forceFill(['type' => TalkAssignmentType::Outgoing])->save();

    $incoming = reminderAssignment($team, $date);
    $incoming->forceFill(['type' => TalkAssignmentType::Incoming])->save();

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 2);
    expect($outgoing->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1)
        ->and($incoming->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);
});

test('queues the d-1 reminder for talks still unconfirmed', function () {
    Queue::fake();

    $team = reminderTeam();
    $assignment = reminderAssignment($team, secondReminderDate());

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(1);
});

test('a reminder sent on a previous day does not block the d-1 reminder', function () {
    Queue::fake();

    $team = reminderTeam();
    $assignment = reminderAssignment($team, secondReminderDate());

    $assignment->notifications()->create([
        'speaker_id' => $assignment->speaker_id,
        'kind' => SpeakerNotificationKind::Reminder,
        'status' => SpeakerNotificationStatus::Sent,
        'sent_at' => now()->subDays(2),
    ])->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->where('kind', SpeakerNotificationKind::Reminder)->count())->toBe(2);
});

test('a manual reminder sent today suppresses the automatic one', function () {
    Queue::fake();

    $team = reminderTeam();
    $assignment = reminderAssignment($team, secondReminderDate());

    $assignment->notifications()->create([
        'speaker_id' => $assignment->speaker_id,
        'kind' => SpeakerNotificationKind::Reminder,
        'status' => SpeakerNotificationStatus::Sent,
        'sent_at' => now(),
    ]);

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('confirmed and rescheduling talks receive no reminder', function () {
    Queue::fake();

    $team = reminderTeam();

    reminderAssignment($team, firstReminderDate())
        ->forceFill(['status' => TalkAssignmentStatus::Confirmed])->save();
    reminderAssignment($team, secondReminderDate())
        ->forceFill(['status' => TalkAssignmentStatus::NeedsReschedule])->save();

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('skips assignments outside the reminder dates or without notifiable speaker', function () {
    Queue::fake();

    $team = reminderTeam();
    $date = firstReminderDate();

    reminderAssignment($team, $date->copy()->addWeek());

    $withoutPhone = reminderAssignment($team, $date);
    $withoutPhone->speaker->forceFill(['phone' => null])->save();

    $withoutOutline = reminderAssignment($team, secondReminderDate());
    $withoutOutline->forceFill(['outline_id' => null])->save();

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('alerts the coordinator about unconfirmed talks on d-0, once per day', function () {
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

test('confirmed talks do not trigger the d-0 alert', function () {
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
    reminderAssignment($team, firstReminderDate());
    $assignment = reminderAssignment($team, secondReminderDate());

    $this->artisan('public-talks:send-speaker-reminders', ['--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});
