<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the current team permissions and cargos are shared to the frontend', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => false]);
    $user->assignCargo($team, DefaultCargo::Secretario->value);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', $team));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('permissions.member:add', true)
        ->where('permissions.invitation:create', true)
        ->missing('permissions.team:delete')
        ->where('cargos.0.key', DefaultCargo::Secretario->value)
        ->where('cargos.0.name', 'Secretário'),
    );
});

test('a member without a cargo in the current team shares empty permissions', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => false]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', $team));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('permissions', [])
        ->where('cargos', []),
    );
});

test('a coordenador shares the full permission catalog', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, DefaultCargo::Coordenador->value);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', $team));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('permissions.team:delete', true)
        ->where('permissions.member:add', true)
        ->where('cargos.0.key', DefaultCargo::Coordenador->value),
    );
});
