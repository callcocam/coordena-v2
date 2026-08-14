<?php

use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Services\PublicTalks\ScheduleHorizon;

function configuredPublicTalksTeam(): Team
{
    $team = Team::factory()->create([
        'home_congregation_id' => Congregation::factory()->create()->id,
    ]);

    Coordinator::factory()->responsible()->create(['team_id' => $team->id]);

    return $team;
}

test('ensures the horizon and the monthly invites for configured teams', function () {
    $team = configuredPublicTalksTeam();

    $this->artisan('public-talks:ensure-horizon')->assertSuccessful();

    expect(TalkAssignment::query()->where('team_id', $team->id)->count())->toBeGreaterThan(0)
        ->and(ExchangeInvite::query()->where('team_id', $team->id)->count())
        ->toBe(ScheduleHorizon::MONTHS_AHEAD);
});

test('ignores teams without the module configured', function () {
    $unconfigured = Team::factory()->create();

    $this->artisan('public-talks:ensure-horizon')->assertSuccessful();

    expect(TalkAssignment::query()->where('team_id', $unconfigured->id)->count())->toBe(0)
        ->and(ExchangeInvite::query()->count())->toBe(0);
});

test('dry-run writes nothing', function () {
    configuredPublicTalksTeam();

    $this->artisan('public-talks:ensure-horizon', ['--dry-run' => true])->assertSuccessful();

    expect(TalkAssignment::query()->count())->toBe(0)
        ->and(ExchangeInvite::query()->count())->toBe(0);
});

test('running twice does not duplicate anything', function () {
    $team = configuredPublicTalksTeam();

    $this->artisan('public-talks:ensure-horizon')->assertSuccessful();
    $assignments = TalkAssignment::query()->where('team_id', $team->id)->count();

    $this->artisan('public-talks:ensure-horizon')->assertSuccessful();

    expect(TalkAssignment::query()->where('team_id', $team->id)->count())->toBe($assignments)
        ->and(ExchangeInvite::query()->where('team_id', $team->id)->count())
        ->toBe(ScheduleHorizon::MONTHS_AHEAD);
});
