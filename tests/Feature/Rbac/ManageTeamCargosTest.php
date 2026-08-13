<?php

use App\Enums\DefaultCargo;
use App\Enums\TeamPermission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function coordenadorFor(Team $team): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, DefaultCargo::Coordenador->value);

    return $user;
}

test('a coordenador can view the cargos management screen', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->get(route('teams.cargos.index', $team))
        ->assertOk();
});

test('a publicador cannot view the cargos management screen', function () {
    $team = Team::factory()->create();
    $publicador = User::factory()->create();
    $team->members()->attach($publicador, ['is_owner' => false]);
    $publicador->assignCargo($team, DefaultCargo::Publicador->value);

    $this
        ->actingAs($publicador)
        ->get(route('teams.cargos.index', $team))
        ->assertForbidden();
});

test('a coordenador can create a custom cargo with synced permissions', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('teams.cargos.store', $team), [
            'name' => 'Coordenador de Limpeza',
            'permissions' => ['member:add', 'service:manage'],
        ])
        ->assertRedirect(route('teams.cargos.index', $team));

    $role = Role::query()->where('team_id', $team->id)->byKey('coordenador-de-limpeza')->first();

    expect($role)->not->toBeNull();
    expect($role->is_super)->toBeFalse();
    expect($role->is_default)->toBeFalse();
    expect($role->permissions->pluck('name')->all())
        ->toContain('member:add')
        ->toContain('service:manage');
});

test('creating a cargo rejects permissions outside the catalog', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('teams.cargos.store', $team), [
            'name' => 'Cargo Inválido',
            'permissions' => ['nao:existe'],
        ])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

test('creating a cargo rejects the reserved role management permission', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('teams.cargos.store', $team), [
            'name' => 'Cargo Escalador',
            'permissions' => [TeamPermission::ManageRoles->value],
        ])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

test('a coordenador can update a custom cargo of their team', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $role = $team->roles()->create([
        'key' => 'cargo-antigo',
        'name' => 'Cargo Antigo',
        'is_default' => false,
        'is_super' => false,
    ]);

    $this
        ->actingAs($coordenador)
        ->put(route('teams.cargos.update', [$team, $role]), [
            'name' => 'Cargo Novo',
            'permissions' => ['member:add'],
        ])
        ->assertRedirect(route('teams.cargos.index', $team));

    $role = $role->fresh();

    expect($role->name)->toBe('Cargo Novo');
    expect($role->key)->toBe('cargo-novo');
    expect($role->permissions->pluck('name')->all())->toEqual(['member:add']);
});

test('a global cargo cannot be updated', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $globalRole = Role::query()->global()->byKey(DefaultCargo::Publicador->value)->firstOrFail();

    $this
        ->actingAs($coordenador)
        ->put(route('teams.cargos.update', [$team, $globalRole]), [
            'name' => 'Hackeado',
            'permissions' => [],
        ])
        ->assertNotFound();

    expect($globalRole->fresh()->name)->not->toBe('Hackeado');
});

test('a coordenador can delete a custom cargo and its assignments', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);
    $member = User::factory()->create();
    $team->members()->attach($member, ['is_owner' => false]);

    $role = $team->roles()->create([
        'key' => 'cargo-descartavel',
        'name' => 'Cargo Descartável',
        'is_default' => false,
        'is_super' => false,
    ]);

    $member->assignCargo($team, $role);

    $this
        ->actingAs($coordenador)
        ->delete(route('teams.cargos.destroy', [$team, $role]))
        ->assertRedirect(route('teams.cargos.index', $team));

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
    expect(DB::table('role_user')->where('role_id', $role->id)->exists())->toBeFalse();
});

test('a global cargo cannot be deleted', function () {
    $team = Team::factory()->create();
    $coordenador = coordenadorFor($team);

    $globalRole = Role::query()->global()->byKey(DefaultCargo::Publicador->value)->firstOrFail();

    $this
        ->actingAs($coordenador)
        ->delete(route('teams.cargos.destroy', [$team, $globalRole]))
        ->assertNotFound();

    expect(Role::query()->whereKey($globalRole->id)->exists())->toBeTrue();
});
