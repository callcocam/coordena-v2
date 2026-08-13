<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\User;

test('a coordenador passes every management action', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, DefaultCargo::Coordenador->value);

    expect($user->can('update', $team))->toBeTrue();
    expect($user->can('delete', $team))->toBeTrue();
    expect($user->can('addMember', $team))->toBeTrue();
    expect($user->can('updateMember', $team))->toBeTrue();
    expect($user->can('removeMember', $team))->toBeTrue();
    expect($user->can('inviteMember', $team))->toBeTrue();
    expect($user->can('cancelInvitation', $team))->toBeTrue();
    expect($user->can('manageRoles', $team))->toBeTrue();
});

test('a publicador is denied every management action', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => false]);
    $user->assignCargo($team, DefaultCargo::Publicador->value);

    expect($user->can('update', $team))->toBeFalse();
    expect($user->can('delete', $team))->toBeFalse();
    expect($user->can('addMember', $team))->toBeFalse();
    expect($user->can('updateMember', $team))->toBeFalse();
    expect($user->can('removeMember', $team))->toBeFalse();
    expect($user->can('inviteMember', $team))->toBeFalse();
    expect($user->can('cancelInvitation', $team))->toBeFalse();
    expect($user->can('manageRoles', $team))->toBeFalse();
});

test('a secretario is allowed its subset but not team deletion', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => false]);
    $user->assignCargo($team, DefaultCargo::Secretario->value);

    expect($user->can('update', $team))->toBeTrue();
    expect($user->can('addMember', $team))->toBeTrue();
    expect($user->can('inviteMember', $team))->toBeTrue();
    expect($user->can('delete', $team))->toBeFalse();
    expect($user->can('manageRoles', $team))->toBeFalse();
});

test('an owner cannot leave the team but a non owner can', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $team->members()->attach($member, ['is_owner' => false]);

    expect($owner->can('leave', $team))->toBeFalse();
    expect($member->can('leave', $team))->toBeTrue();
});

test('nobody can delete a personal team even as coordenador', function () {
    $user = User::factory()->create();
    $personalTeam = $user->personalTeam();

    $user->assignCargo($personalTeam, DefaultCargo::Coordenador->value);

    expect($user->can('delete', $personalTeam))->toBeFalse();
});
