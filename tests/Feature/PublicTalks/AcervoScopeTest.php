<?php

use App\Enums\DefaultCargo;
use App\Models\Congregation;
use App\Models\Team;
use App\Models\User;

function acervoUserWithTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    return [$user->fresh(), $team];
}

test('coordenador can manage congregations of the current team owner acervo', function () {
    [$user] = acervoUserWithTeam();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);

    expect($user->can('viewAny', Congregation::class))->toBeTrue()
        ->and($user->can('view', $congregation))->toBeTrue()
        ->and($user->can('update', $congregation))->toBeTrue()
        ->and($user->can('delete', $congregation))->toBeTrue();
});

test('congregations from another owner acervo are denied even with permission', function () {
    [$user] = acervoUserWithTeam();

    $foreign = Congregation::factory()->create();

    expect($user->can('view', $foreign))->toBeFalse()
        ->and($user->can('update', $foreign))->toBeFalse()
        ->and($user->can('delete', $foreign))->toBeFalse();
});

test('publicador cannot manage the acervo', function () {
    [$user] = acervoUserWithTeam(DefaultCargo::Publicador->value);

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);

    expect($user->can('create', Congregation::class))->toBeFalse()
        ->and($user->can('update', $congregation))->toBeFalse();
});

test('a user without a current team sees nothing', function () {
    $user = User::factory()->create();
    $user->forceFill(['current_team_id' => null])->save();

    expect($user->fresh()->can('viewAny', Congregation::class))->toBeFalse();
});
