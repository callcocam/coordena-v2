<?php

use App\Enums\DefaultCargo;
use App\Enums\SpeakerRole;
use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team}
 */
function acervoHttpUser(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    return [$user->fresh(), $team];
}

test('publicador cannot open the acervo', function () {
    [$user, $team] = acervoHttpUser(DefaultCargo::Publicador->value);

    $this->actingAs($user)
        ->get(route('acervo.congregations.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('the acervo lists only congregations of the team owner', function () {
    [$user, $team] = acervoHttpUser();

    Congregation::factory()->create(['owner_user_id' => $user->id, 'name' => 'Congregação Local']);
    Congregation::factory()->create(['name' => 'Acervo Alheio']);

    $this->actingAs($user)
        ->get(route('acervo.congregations.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/congregations/Index')
            ->has('congregations', 1)
            ->where('congregations.0.name', 'Congregação Local'),
        );
});

test('coordenador can create a congregation in the owner acervo', function () {
    [$user, $team] = acervoHttpUser();

    $response = $this->actingAs($user)
        ->post(route('acervo.congregations.store', ['current_team' => $team->slug]), [
            'name' => 'Congregação Nova',
            'city' => 'Lajeado',
        ]);

    $congregation = Congregation::query()->where('name', 'Congregação Nova')->firstOrFail();

    $response->assertRedirect(route('acervo.congregations.show', [
        'current_team' => $team->slug,
        'congregation' => $congregation->id,
    ]));

    expect($congregation->owner_user_id)->toBe($user->id);
});

test('a congregation from another acervo cannot be seen', function () {
    [$user, $team] = acervoHttpUser();

    $foreign = Congregation::factory()->create();

    $this->actingAs($user)
        ->get(route('acervo.congregations.show', [
            'current_team' => $team->slug,
            'congregation' => $foreign->id,
        ]))
        ->assertForbidden();
});

test('coordenador can update a congregation', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('acervo.congregations.update', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
        ]), ['name' => 'Nome Atualizado', 'meeting_weekday' => 0, 'meeting_time' => '18:00'])
        ->assertRedirect();

    $congregation->refresh();

    expect($congregation->name)->toBe('Nome Atualizado')
        ->and($congregation->meeting_weekday)->toBe(0);
});

test('the home congregation of a team cannot be deleted', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $congregation->id])->save();

    $this->actingAs($user)
        ->delete(route('acervo.congregations.destroy', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
        ]))
        ->assertSessionHasErrors('congregation');

    expect($congregation->fresh()->trashed())->toBeFalse();
});

test('an unused congregation can be deleted', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('acervo.congregations.destroy', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
        ]))
        ->assertRedirect(route('acervo.congregations.index', ['current_team' => $team->slug]));

    expect(Congregation::withTrashed()->find($congregation->id)->trashed())->toBeTrue();
});

test('coordenador can add a speaker with outlines to a congregation', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $outline = PublicTalkOutline::factory()->create();

    $this->actingAs($user)
        ->post(route('acervo.speakers.store', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
        ]), [
            'name' => 'Orador Novo',
            'role' => SpeakerRole::Elder->value,
            'phone' => '51999990000',
            'is_active' => true,
            'outline_ids' => [$outline->id],
        ])
        ->assertRedirect();

    $speaker = Speaker::query()->where('name', 'Orador Novo')->firstOrFail();

    expect($speaker->congregation_id)->toBe($congregation->id)
        ->and($speaker->outlines->modelKeys())->toBe([$outline->id]);
});

test('a speaker cannot be updated through another congregation', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $other = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $speaker = Speaker::factory()->create(['congregation_id' => $other->id]);

    $this->actingAs($user)
        ->put(route('acervo.speakers.update', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
            'speaker' => $speaker->id,
        ]), ['name' => 'Trocado', 'role' => SpeakerRole::Elder->value])
        ->assertNotFound();
});

test('coordenador can remove a speaker', function () {
    [$user, $team] = acervoHttpUser();

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $speaker = Speaker::factory()->create(['congregation_id' => $congregation->id]);

    $this->actingAs($user)
        ->delete(route('acervo.speakers.destroy', [
            'current_team' => $team->slug,
            'congregation' => $congregation->id,
            'speaker' => $speaker->id,
        ]))
        ->assertRedirect();

    expect(Speaker::withTrashed()->find($speaker->id)->trashed())->toBeTrue();
});
