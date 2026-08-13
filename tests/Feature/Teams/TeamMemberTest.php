<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\User;

test('team member cargos can be updated by authorized members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'cargos' => [DefaultCargo::Secretario->value],
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    $member = $member->fresh();

    expect($member->hasCargo($team, DefaultCargo::Secretario->value))->toBeTrue();
    expect($member->hasCargo($team, DefaultCargo::Publicador->value))->toBeFalse();
});

test('team member cargos update reflects the union of multiple cargos', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'cargos' => [
                DefaultCargo::Secretario->value,
                DefaultCargo::SuperintendenteServico->value,
            ],
        ])
        ->assertRedirect(route('teams.edit', $team));

    $member = $member->fresh();
    $permissions = $member->permissionNamesForTeam($team);

    expect($member->hasCargo($team, DefaultCargo::Secretario->value))->toBeTrue();
    expect($member->hasCargo($team, DefaultCargo::SuperintendenteServico->value))->toBeTrue();
    expect($permissions)->toContain('member:add');
    expect($permissions)->toContain('service:manage');
    expect($permissions)->not->toContain('team:delete');
});

test('team member cargos cannot be updated by unauthorized members', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($actor, ['is_owner' => false]);
    $actor->assignCargo($team, DefaultCargo::Publicador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($actor)
        ->patch(route('teams.members.update', [$team, $member]), [
            'cargos' => [DefaultCargo::Secretario->value],
        ]);

    $response->assertForbidden();
});

test('team members can be removed by authorized members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.edit', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members cannot be removed by unauthorized members', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($actor, ['is_owner' => false]);
    $actor->assignCargo($team, DefaultCargo::Publicador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($actor)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertForbidden();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team member cargos cannot be set to a super cargo', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'cargos' => [DefaultCargo::Coordenador->value],
        ]);

    $response->assertSessionHasErrors('cargos.0');

    $member = $member->fresh();

    expect($member->hasCargo($team, DefaultCargo::Coordenador->value))->toBeFalse();
    expect($member->hasCargo($team, DefaultCargo::Publicador->value))->toBeTrue();
});

test('removed member current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});
