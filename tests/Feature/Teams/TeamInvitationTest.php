<?php

use App\Enums\DefaultCargo;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;

test('team invitations can be created', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role_key' => DefaultCargo::Publicador->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    $this->assertDatabaseHas('team_invitations', [
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role_key' => DefaultCargo::Publicador->value,
    ]);
});

test('invitation email for existing users uses login route', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => $invitedUser->email,
        'invited_by' => $owner->id,
    ]);

    $mail = (new TeamInvitationNotification($invitation))->toMail($invitedUser);

    expect($mail->actionUrl)->toBe(route('login', ['invitation' => $invitation->code]));
    $this->assertStringContainsString('dashboard', implode(' ', $mail->introLines));
});

test('invitation email for unknown users uses login route', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'unknown@example.com',
        'invited_by' => $owner->id,
    ]);

    $mail = (new TeamInvitationNotification($invitation))->toMail((object) []);

    expect($mail->actionUrl)->toBe(route('login', ['invitation' => $invitation->code]));
    $this->assertStringContainsString('log in', strtolower(implode(' ', $mail->introLines)));
});

test('team invitations can be created by secretaries', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $secretary = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($secretary, ['is_owner' => false]);
    $secretary->assignCargo($team, DefaultCargo::Secretario->value);

    $response = $this
        ->actingAs($secretary)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role_key' => DefaultCargo::Publicador->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));
});

test('existing team members cannot be invited', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'member@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'member@example.com',
            'role_key' => DefaultCargo::Publicador->value,
        ]);

    $response->assertSessionHasErrors('email');
});

test('duplicate invitations cannot be created', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role_key' => DefaultCargo::Publicador->value,
        ]);

    $response->assertSessionHasErrors('email');
});

test('team invitations cannot be created by unauthorized members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $team->members()->attach($member, ['is_owner' => false]);
    $member->assignCargo($team, DefaultCargo::Publicador->value);

    $response = $this
        ->actingAs($member)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'invited@example.com',
            'role_key' => DefaultCargo::Publicador->value,
        ]);

    $response->assertForbidden();
});

test('team invitations can be cancelled by authorized members', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);
    $owner->assignCargo($team, DefaultCargo::Coordenador->value);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.invitations.destroy', [$team, $invitation]));

    $response->assertRedirect(route('teams.edit', $team));

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('team invitations can be accepted', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role_key' => DefaultCargo::Secretario->value,
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->post(route('invitations.accept', $invitation));

    $response->assertRedirect(route('dashboard'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Invitation accepted.']);

    $invitedUser = $invitedUser->fresh();

    expect($invitedUser->belongsToTeam($team))->toBeTrue();
    expect($invitedUser->hasCargo($team, DefaultCargo::Secretario->value))->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('accepting an invitation without a cargo assigns the base publicador cargo', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'role_key' => null,
        'invited_by' => $owner->id,
    ]);

    $this
        ->actingAs($invitedUser)
        ->post(route('invitations.accept', $invitation));

    expect($invitedUser->fresh()->hasCargo($team, DefaultCargo::Publicador->value))->toBeTrue();
});

test('team invitations can be declined by the invited user', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->delete(route('invitations.decline', $invitation));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('team invitations cannot be declined by uninvited user', function () {
    $owner = User::factory()->create();
    $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($uninvitedUser)
        ->delete(route('invitations.decline', $invitation));

    $response->assertSessionHasErrors('invitation');

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('accepted team invitations cannot be declined', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->delete(route('invitations.decline', $invitation));

    $response->assertSessionHasErrors('invitation');

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('team invitations cannot be accepted by uninvited user', function () {
    $owner = User::factory()->create();
    $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($uninvitedUser)
        ->post(route('invitations.accept', $invitation));

    $response->assertSessionHasErrors('invitation');

    expect($uninvitedUser->fresh()->belongsToTeam($team))->toBeFalse();
});

test('expired invitations cannot be accepted', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['is_owner' => true]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->post(route('invitations.accept', $invitation));

    $response->assertSessionHasErrors('invitation');

    expect($invitedUser->fresh()->belongsToTeam($team))->toBeFalse();
});
