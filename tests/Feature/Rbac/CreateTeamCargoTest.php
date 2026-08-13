<?php

use App\Actions\Teams\CreateTeam;
use App\Enums\DefaultCargo;
use App\Models\User;

test('creating a team makes the creator an owner with the coordenador cargo', function () {
    $user = User::factory()->create();

    $team = app(CreateTeam::class)->handle($user, 'Congregação Central');

    expect($user->ownsTeam($team))->toBeTrue();
    expect($user->hasCargo($team, DefaultCargo::Coordenador->value))->toBeTrue();
    expect($user->hasPermissionForTeam($team, 'team:delete'))->toBeTrue();
});
