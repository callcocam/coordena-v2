<?php

use App\Models\Coordinator;
use App\Models\Team;
use App\Services\PublicTalks\ResponsibleCoordinator;

beforeEach(function () {
    $this->service = app(ResponsibleCoordinator::class);
});

test('resolves the active responsible coordinator of the team', function () {
    $team = Team::factory()->create();

    Coordinator::factory()->create(['team_id' => $team->id]);
    $responsible = Coordinator::factory()->responsible()->create(['team_id' => $team->id]);
    Coordinator::factory()->responsible()->inactive()->create(['team_id' => $team->id]);

    expect($this->service->for($team)?->id)->toBe($responsible->id);
});

test('returns null when the team has no active responsible', function () {
    $team = Team::factory()->create();

    Coordinator::factory()->create(['team_id' => $team->id]);
    Coordinator::factory()->responsible()->inactive()->create(['team_id' => $team->id]);

    expect($this->service->for($team))->toBeNull();
});

test('recipients are active coordinators with phone, responsible first', function () {
    $team = Team::factory()->create();

    $helper = Coordinator::factory()->create(['team_id' => $team->id]);
    $responsible = Coordinator::factory()->responsible()->create(['team_id' => $team->id]);
    Coordinator::factory()->create(['team_id' => $team->id, 'phone' => null]);
    Coordinator::factory()->inactive()->create(['team_id' => $team->id]);

    $recipients = $this->service->recipientsFor($team);

    expect($recipients->pluck('id')->all())->toBe([$responsible->id, $helper->id]);
});
