<?php

use App\Enums\ExchangeInviteStatus;
use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\ExchangeInvite;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ExchangeInviteManager;
use Illuminate\Support\Carbon;

test('forMonth creates a single invite per team and month', function () {
    $team = Team::factory()->create();
    $month = Carbon::today()->addMonth()->startOfMonth();
    $manager = app(ExchangeInviteManager::class);

    $first = $manager->forMonth($team, $month);
    $second = $manager->forMonth($team, $month);

    expect($second->id)->toBe($first->id)
        ->and(ExchangeInvite::query()->where('team_id', $team->id)->count())->toBe(1);
});

test('openWeeks returns only the open home weeks of the invite month', function () {
    $team = Team::factory()->create();
    $month = Carbon::today()->addMonth()->startOfMonth();

    $open = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addDays(5)->toDateString(),
    ]);
    TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addDays(12)->toDateString(),
        'status' => TalkAssignmentStatus::Scheduled,
    ]);
    TalkAssignment::factory()->outgoing()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addDays(19)->toDateString(),
    ]);
    TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addMonth()->addDays(5)->toDateString(),
    ]);

    $manager = app(ExchangeInviteManager::class);
    $invite = $manager->forMonth($team, $month);

    expect($manager->openWeeks($invite)->pluck('id')->all())->toBe([$open->id]);
});

test('refreshStatus reflects the fill state of the month weeks', function () {
    $team = Team::factory()->create();
    $month = Carbon::today()->addMonth()->startOfMonth();

    $first = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addDays(5)->toDateString(),
    ]);
    $second = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->addDays(12)->toDateString(),
    ]);

    $manager = app(ExchangeInviteManager::class);
    $invite = $manager->forMonth($team, $month);

    expect($invite->status)->toBe(ExchangeInviteStatus::Open);

    $first->forceFill(['type' => TalkAssignmentType::Incoming, 'status' => TalkAssignmentStatus::Scheduled])->save();
    expect($manager->refreshStatus($invite)->status)->toBe(ExchangeInviteStatus::PartiallyFilled);

    $second->forceFill(['status' => TalkAssignmentStatus::Scheduled])->save();
    expect($manager->refreshStatus($invite)->status)->toBe(ExchangeInviteStatus::Filled);
});

test('an invite of a past month expires', function () {
    $team = Team::factory()->create();
    $invite = ExchangeInvite::factory()->create([
        'team_id' => $team->id,
        'month' => Carbon::today()->subMonths(2)->startOfMonth()->toDateString(),
    ]);

    expect(app(ExchangeInviteManager::class)->refreshStatus($invite)->status)
        ->toBe(ExchangeInviteStatus::Expired);
});
