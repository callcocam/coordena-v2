<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Models\User;
use App\Models\WhatsappTermsAcceptance;
use App\Support\WhatsappTerms;

function whatsappCoordenadorFor(Team $team): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, DefaultCargo::Coordenador->value);
    $user->switchTeam($team);

    return $user;
}

test('a coordenador can accept the whatsapp terms of use', function () {
    $team = Team::factory()->create();
    $coordenador = whatsappCoordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('whatsapp.agree'))
        ->assertRedirect(route('profile.edit'));

    $acceptance = WhatsappTermsAcceptance::query()
        ->where('team_id', $team->id)
        ->where('user_id', $coordenador->id)
        ->first();

    expect($acceptance)->not->toBeNull();
    expect($acceptance->version)->toBe(WhatsappTerms::VERSION);
    expect($acceptance->accepted_at)->not->toBeNull();
});

test('a coordenador can switch the team to manual mode', function () {
    $team = Team::factory()->create(['whatsapp_api_enabled' => true]);
    $coordenador = whatsappCoordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->patch(route('whatsapp.mode'), ['api_enabled' => false])
        ->assertRedirect(route('profile.edit'));

    expect($team->fresh()->whatsapp_api_enabled)->toBeFalse();
});

test('a coordenador can save the team meta cloud credentials', function () {
    $team = Team::factory()->create();
    $coordenador = whatsappCoordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('whatsapp.cloud.save'), [
            'phone_number_id' => '1234567890',
            'cloud_access_token' => 'EAAG-fake-token',
            'waba_id' => '9876543210',
            'app_id' => '5555',
            'verified_name' => 'Congregação Central',
        ])
        ->assertRedirect(route('profile.edit'));

    $connection = TeamWhatsappConnection::query()->where('team_id', $team->id)->first();

    expect($connection)->not->toBeNull();
    expect($connection->phone_number_id)->toBe('1234567890');
    expect($connection->cloud_access_token)->toBe('EAAG-fake-token');
    expect($connection->hasCloudCredentials())->toBeTrue();
});

test('saving cloud credentials requires phone number id and token', function () {
    $team = Team::factory()->create();
    $coordenador = whatsappCoordenadorFor($team);

    $this
        ->actingAs($coordenador)
        ->post(route('whatsapp.cloud.save'), [
            'phone_number_id' => '',
            'cloud_access_token' => '',
        ])
        ->assertSessionHasErrors(['phone_number_id', 'cloud_access_token']);

    expect(TeamWhatsappConnection::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

test('a publicador cannot manage the whatsapp connection', function () {
    $team = Team::factory()->create();
    $publicador = User::factory()->create();
    $team->members()->attach($publicador, ['is_owner' => false]);
    $publicador->assignCargo($team, DefaultCargo::Publicador->value);
    $publicador->switchTeam($team);

    $this
        ->actingAs($publicador)
        ->patch(route('whatsapp.mode'), ['api_enabled' => false])
        ->assertForbidden();

    $this
        ->actingAs($publicador)
        ->post(route('whatsapp.cloud.save'), [
            'phone_number_id' => '1234567890',
            'cloud_access_token' => 'EAAG-fake-token',
        ])
        ->assertForbidden();
});
