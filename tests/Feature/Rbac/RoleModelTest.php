<?php

use App\Enums\DefaultCargo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Support\PermissionCatalog;

test('the seeder registers every global cargo', function () {
    $keys = Role::query()->global()->pluck('key');

    foreach (DefaultCargo::cases() as $cargo) {
        expect($keys)->toContain($cargo->value);
    }
});

test('the coordenador cargo is a super role and grants all permissions', function () {
    $coordenador = Role::query()->byKey(DefaultCargo::Coordenador->value)->global()->firstOrFail();

    expect($coordenador->is_super)->toBeTrue();
    expect($coordenador->grantsAll())->toBeTrue();
});

test('the publicador cargo is the default base role', function () {
    $publicador = Role::query()->byKey(DefaultCargo::Publicador->value)->global()->firstOrFail();

    expect($publicador->is_default)->toBeTrue();

    $defaultKeys = Role::query()->global()->default()->pluck('key');

    expect($defaultKeys)->toContain(DefaultCargo::Publicador->value);
});

test('the secretario cargo grants member management but not team deletion', function () {
    $secretario = Role::query()->byKey(DefaultCargo::Secretario->value)->global()->firstOrFail();

    $names = $secretario->permissions->pluck('name');

    expect($names)->toContain('member:add');
    expect($names)->toContain('invitation:create');
    expect($names)->not->toContain('team:delete');
});

test('every seeded permission belongs to the catalog', function () {
    $names = Permission::query()->pluck('name');

    expect($names->sort()->values()->all())->toEqual(collect(PermissionCatalog::names())->sort()->values()->all());
});

test('assignableForTeam excludes super cargos', function () {
    $team = Team::factory()->create();

    $assignableKeys = Role::query()->assignableForTeam($team)->pluck('key');

    expect($assignableKeys)->not->toContain(DefaultCargo::Coordenador->value);
    expect($assignableKeys)->toContain(DefaultCargo::Publicador->value);
    expect($assignableKeys)->toContain(DefaultCargo::Secretario->value);
});

test('assignableForTeam includes the team custom cargos but not other teams', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $custom = Role::factory()->create(['team_id' => $team->id, 'key' => 'ancianos-locais']);
    $otherCustom = Role::factory()->create(['team_id' => $otherTeam->id, 'key' => 'outro-custom']);

    $assignableKeys = Role::query()->assignableForTeam($team)->pluck('key');

    expect($assignableKeys)->toContain($custom->key);
    expect($assignableKeys)->not->toContain($otherCustom->key);
});
