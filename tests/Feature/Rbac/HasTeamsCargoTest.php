<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\User;
use App\Support\PermissionCatalog;

test('a cargo can be assigned to a user scoped to a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Secretario->value);

    expect($user->hasCargo($team, DefaultCargo::Secretario->value))->toBeTrue();
    expect($user->cargosForTeam($team)->pluck('key'))->toContain(DefaultCargo::Secretario->value);
});

test('assigning the same cargo twice does not duplicate the assignment', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Secretario->value);
    $user->assignCargo($team, DefaultCargo::Secretario->value);

    expect($user->cargosForTeam($team)->where('key', DefaultCargo::Secretario->value))->toHaveCount(1);
});

test('a cargo can be removed from a user for a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Secretario->value);
    $user->removeCargo($team, DefaultCargo::Secretario->value);

    expect($user->hasCargo($team, DefaultCargo::Secretario->value))->toBeFalse();
});

test('syncCargos replaces the existing cargos for the team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Publicador->value);
    $user->syncCargos($team, [DefaultCargo::Secretario->value, DefaultCargo::SuperintendenteServico->value]);

    $keys = $user->cargosForTeam($team)->pluck('key');

    expect($keys)->toContain(DefaultCargo::Secretario->value);
    expect($keys)->toContain(DefaultCargo::SuperintendenteServico->value);
    expect($keys)->not->toContain(DefaultCargo::Publicador->value);
});

test('effective permissions are the union of every cargo the user holds', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->syncCargos($team, [DefaultCargo::SuperintendenteServico->value, DefaultCargo::Secretario->value]);

    $permissions = $user->permissionNamesForTeam($team);

    // From secretario
    expect($permissions)->toContain('member:add');
    expect($permissions)->toContain('invitation:create');
    // From superintendente-servico
    expect($permissions)->toContain('service:manage');
    // Neither grants team deletion
    expect($permissions)->not->toContain('team:delete');
});

test('a super cargo short circuits to the entire permission catalog', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Coordenador->value);

    $permissions = $user->permissionNamesForTeam($team);

    expect($permissions->sort()->values()->all())
        ->toEqual(collect(PermissionCatalog::names())->sort()->values()->all());

    expect($user->hasPermissionForTeam($team, 'team:delete'))->toBeTrue();
});

test('a publicador only holds the base congregation view permission', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->assignCargo($team, DefaultCargo::Publicador->value);

    expect($user->hasPermissionForTeam($team, 'congregation:view'))->toBeTrue();
    expect($user->hasPermissionForTeam($team, 'team:delete'))->toBeFalse();
    expect($user->hasPermissionForTeam($team, 'member:add'))->toBeFalse();
});

test('cargos are isolated between different teams', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $user->assignCargo($teamA, DefaultCargo::Coordenador->value);
    $user->assignCargo($teamB, DefaultCargo::Publicador->value);

    expect($user->hasPermissionForTeam($teamA, 'team:delete'))->toBeTrue();
    expect($user->hasPermissionForTeam($teamB, 'team:delete'))->toBeFalse();

    expect($user->hasCargo($teamA, DefaultCargo::Coordenador->value))->toBeTrue();
    expect($user->hasCargo($teamB, DefaultCargo::Coordenador->value))->toBeFalse();
});

test('a user without any cargo in a team has no permissions there', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect($user->permissionNamesForTeam($team))->toBeEmpty();
    expect($user->hasPermissionForTeam($team, 'congregation:view'))->toBeFalse();
});

test('ownsTeam is driven by the is_owner pivot flag', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $team->members()->attach($member, ['is_owner' => false]);

    expect($owner->ownsTeam($team))->toBeTrue();
    expect($member->ownsTeam($team))->toBeFalse();
});
