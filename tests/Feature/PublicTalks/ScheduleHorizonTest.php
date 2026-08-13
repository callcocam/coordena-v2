<?php

use App\Enums\TalkAssignmentStatus;
use App\Enums\TalkAssignmentType;
use App\Models\Congregation;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ScheduleHorizon;
use Illuminate\Support\Carbon;

test('creates open home assignments for the next three months on the meeting weekday', function () {
    $congregation = Congregation::factory()->create(['meeting_weekday' => Carbon::SUNDAY]);
    $team = Team::factory()->create(['home_congregation_id' => $congregation->id]);

    $created = app(ScheduleHorizon::class)->ensure($team);

    $assignments = TalkAssignment::query()->where('team_id', $team->id)->get();

    expect($created)->toBeGreaterThan(0)
        ->and($assignments)->toHaveCount($created);

    $lastMonth = Carbon::today()->startOfMonth()->addMonths(ScheduleHorizon::MONTHS_AHEAD - 1);

    foreach ($assignments as $assignment) {
        expect($assignment->type)->toBe(TalkAssignmentType::Home)
            ->and($assignment->status)->toBe(TalkAssignmentStatus::Open)
            ->and($assignment->date->dayOfWeek)->toBe(Carbon::SUNDAY)
            ->and($assignment->date->gte(Carbon::today()))->toBeTrue()
            ->and($assignment->date->lte($lastMonth->copy()->endOfMonth()))->toBeTrue();
    }
});

test('defaults to saturday when the team has no home congregation', function () {
    $team = Team::factory()->create();

    app(ScheduleHorizon::class)->ensure($team);

    $weekdays = TalkAssignment::query()
        ->where('team_id', $team->id)
        ->get()
        ->map(fn (TalkAssignment $assignment): int => $assignment->date->dayOfWeek)
        ->unique();

    expect($weekdays->all())->toBe([Carbon::SATURDAY]);
});

test('running twice does not duplicate assignments', function () {
    $team = Team::factory()->create();
    $horizon = app(ScheduleHorizon::class);

    $first = $horizon->ensure($team);
    $second = $horizon->ensure($team);

    expect($second)->toBe(0)
        ->and(TalkAssignment::query()->where('team_id', $team->id)->count())->toBe($first);
});

test('creates only the missing weekends when part of the horizon already exists', function () {
    $team = Team::factory()->create();
    $horizon = app(ScheduleHorizon::class);

    $horizon->ensure($team);

    $removed = TalkAssignment::query()
        ->where('team_id', $team->id)
        ->orderByDesc('date')
        ->first();
    $removed->delete();

    expect($horizon->ensure($team))->toBe(1);
});
