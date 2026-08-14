<?php

use App\Enums\DefaultCargo;
use App\Enums\TalkAssignmentStatus;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team}
 */
function scheduleUserWithTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    return [$user->fresh(), $team];
}

/**
 * @return array{0: User, 1: Team, 2: Congregation}
 */
function scheduleReadyTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    [$user, $team] = scheduleUserWithTeam($cargo);

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $congregation->id])->save();

    Coordinator::factory()->responsible()->create(['team_id' => $team->id]);

    return [$user, $team->fresh(), $congregation];
}

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();

    $this->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('publicador cannot open the schedule', function () {
    [$user, $team] = scheduleUserWithTeam(DefaultCargo::Publicador->value);

    $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('setup asks for the home congregation when the team has none', function () {
    [$user, $team] = scheduleUserWithTeam();

    $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Setup')
            ->where('step', 'congregation')
            ->where('canManage', true),
        );
});

test('setup asks for the responsible coordinator when only the congregation is set', function () {
    [$user, $team] = scheduleUserWithTeam();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $congregation->id])->save();

    $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Setup')
            ->where('step', 'coordinator'),
        );
});

test('a ready team sees the schedule with generated weeks', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $response = $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('publicTalks/Schedule')
        ->where('month', Carbon::today()->format('Y-m'))
        ->where('homeCongregation.id', $congregation->id)
        ->where('canManage', true),
    );

    expect(TalkAssignment::query()->where('team_id', $team->id)->count())->toBeGreaterThan(0);
});

test('a busy speaker is offered as unavailable in the schedule payload', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $free = Speaker::factory()->create(['congregation_id' => $congregation->id, 'name' => 'Orador Livre']);
    $busy = Speaker::factory()->create(['congregation_id' => $congregation->id, 'name' => 'Orador Ocupado']);

    TalkAssignment::factory()->outgoing()->confirmed()->create([
        'team_id' => $team->id,
        'speaker_id' => $busy->id,
        'date' => Carbon::today()->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Schedule')
            ->where('speakers.0.name', 'Orador Livre')
            ->where('speakers.0.available', true)
            ->where('speakers.1.name', 'Orador Ocupado')
            ->where('speakers.1.available', false),
        );
});

test('the outline catalog exposes the reference url for the picker', function () {
    [$user, $team] = scheduleReadyTeam();

    $outline = PublicTalkOutline::factory()->create([
        'number' => 1,
        'reference_url' => 'https://example.org/esboco-1',
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Schedule')
            ->where('outlines.0.id', $outline->id)
            ->where('outlines.0.reference_url', 'https://example.org/esboco-1'),
        );
});

test('a home slot accepts a speaker from the home acervo', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $speaker = Speaker::factory()->create(['congregation_id' => $congregation->id]);
    $outline = PublicTalkOutline::factory()->create();
    $assignment = TalkAssignment::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->put(route('public-talks.schedule.update', [
            'current_team' => $team->slug,
            'assignment' => $assignment->id,
        ]), ['speaker_id' => $speaker->id, 'outline_id' => $outline->id])
        ->assertRedirect();

    $assignment->refresh();

    expect($assignment->speaker_id)->toBe($speaker->id)
        ->and($assignment->outline_id)->toBe($outline->id)
        ->and($assignment->status)->toBe(TalkAssignmentStatus::Scheduled)
        ->and($assignment->created_by_id)->toBe($user->id);
});

test('a home slot rejects a speaker from another congregation', function () {
    [$user, $team] = scheduleReadyTeam();

    $foreign = Speaker::factory()->create();
    $assignment = TalkAssignment::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->from(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->put(route('public-talks.schedule.update', [
            'current_team' => $team->slug,
            'assignment' => $assignment->id,
        ]), ['speaker_id' => $foreign->id])
        ->assertSessionHasErrors('speaker_id');

    expect($assignment->refresh()->speaker_id)->toBeNull();
});

test('clearing the speaker reopens the slot', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $speaker = Speaker::factory()->create(['congregation_id' => $congregation->id]);
    $assignment = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'speaker_id' => $speaker->id,
        'status' => TalkAssignmentStatus::Scheduled,
    ]);

    $this->actingAs($user)
        ->put(route('public-talks.schedule.update', [
            'current_team' => $team->slug,
            'assignment' => $assignment->id,
        ]), ['speaker_id' => null])
        ->assertRedirect();

    $assignment->refresh();

    expect($assignment->speaker_id)->toBeNull()
        ->and($assignment->outline_id)->toBeNull()
        ->and($assignment->status)->toBe(TalkAssignmentStatus::Open);
});

test('an outgoing slot cannot be edited', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $speaker = Speaker::factory()->create(['congregation_id' => $congregation->id]);
    $assignment = TalkAssignment::factory()->outgoing()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->put(route('public-talks.schedule.update', [
            'current_team' => $team->slug,
            'assignment' => $assignment->id,
        ]), ['speaker_id' => $speaker->id])
        ->assertForbidden();
});

test('a slot from another team cannot be edited', function () {
    [$user, $team, $congregation] = scheduleReadyTeam();

    $speaker = Speaker::factory()->create(['congregation_id' => $congregation->id]);
    $assignment = TalkAssignment::factory()->create();

    $this->actingAs($user)
        ->put(route('public-talks.schedule.update', [
            'current_team' => $team->slug,
            'assignment' => $assignment->id,
        ]), ['speaker_id' => $speaker->id])
        ->assertForbidden();
});
