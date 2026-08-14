<?php

use App\Enums\DefaultCargo;
use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team, 2: Congregation}
 */
function exchangeTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $congregation->id])->save();

    return [$user->fresh(), $team->fresh(), $congregation];
}

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();

    $this->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('publicador cannot open the exchange page', function () {
    [$user, $team] = exchangeTeam(DefaultCargo::Publicador->value);

    $this->actingAs($user)
        ->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('coordinator sees the monthly invite with the round-robin suggestion', function () {
    [$user, $team] = exchangeTeam();

    Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Exchange')
            ->has('invite')
            ->has('candidates', 1)
            ->has('suggestionId')
            ->where('months.0', Carbon::today()->format('Y-m')));

    expect(ExchangeInvite::query()->where('team_id', $team->id)->count())->toBe(1);
});

test('the rotation list exposes whatsapp, last invite date and pre-selects the suggestion', function () {
    [$user, $team] = exchangeTeam();

    $withPhone = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);
    $withoutPhone = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => null,
        'secretary_phone' => null,
        'contact_email' => 'contato@exemplo.test',
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Exchange')
            ->has('candidates', 2)
            ->has('candidates.0', fn (Assert $candidate) => $candidate
                ->hasAll(['id', 'name', 'city', 'has_whatsapp', 'last_invited_at'])
                ->etc())
            ->where('selectedId', fn ($selectedId) => $selectedId !== null)
            ->where('composeText', fn ($composeText) => is_string($composeText) && $composeText !== ''));

    expect($withPhone)->not->toBeNull()
        ->and($withoutPhone)->not->toBeNull();
});

test('registering a manual send creates the portal token and the outbound message', function () {
    [$user, $team] = exchangeTeam();

    $partner = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);
    $month = Carbon::today()->addMonth();

    $this->actingAs($user)
        ->post(route('public-talks.exchange.sends.store', ['current_team' => $team->slug]), [
            'month' => $month->format('Y-m'),
            'congregation_id' => $partner->id,
        ])
        ->assertRedirect();

    $send = ExchangeInviteSend::query()->where('congregation_id', $partner->id)->first();

    expect($send)->not->toBeNull()
        ->and($send->status)->toBe(ExchangeInviteSendStatus::Sent)
        ->and($send->portal_token)->not->toBeNull()
        ->and($send->messages()->where('direction', 'outbound')->count())->toBe(1);
});

test('the outbound invite message includes the home meeting time in the week lines', function () {
    [$user, $team, $congregation] = exchangeTeam();

    $congregation->forceFill(['meeting_time' => '18:30'])->save();

    $partner = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);
    $month = Carbon::today()->addMonth();

    TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $month->copy()->startOfMonth()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('public-talks.exchange.sends.store', ['current_team' => $team->slug]), [
            'month' => $month->format('Y-m'),
            'congregation_id' => $partner->id,
        ])
        ->assertRedirect();

    $message = ExchangeMessage::query()->where('direction', 'outbound')->firstOrFail();

    expect($message->body)->toContain('às 18:30');
});

test('the public portal renders through the token without authentication', function () {
    [, $team] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('a', 48),
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/ExchangePortal')
            ->where('closed', false)
            ->where('meetingTime', substr((string) $team->homeCongregation->meeting_time, 0, 5))
            ->where('month', $invite->month->format('Y-m')));
});

test('a whatsapp send hides the speakers on the portal until the invite is accepted', function () {
    [, $team, $congregation] = exchangeTeam();

    Speaker::factory()->create(['congregation_id' => $congregation->id, 'name' => 'João Livre']);

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('d', 48),
        'channel' => 'whatsapp',
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('homeSpeakers', []));

    $send->update(['status' => ExchangeInviteSendStatus::Accepted, 'accepted_at' => now()]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('homeSpeakers', 1)
            ->where('homeSpeakers.0.name', 'João Livre'));
});

test('the portal returns 404 for an unknown token', function () {
    $this->get(route('exchange.portal', ['portal_token' => str_repeat('x', 48)]))
        ->assertNotFound();
});

test('a portal submission stores draft offers with an inactive speaker and marks the send answered', function () {
    [, $team] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('b', 48),
        'status' => ExchangeInviteSendStatus::Sent,
    ]);
    $date = $invite->month->copy()->addDays(5)->toDateString();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'offers' => [
            ['speaker_name' => 'João Visitante', 'phone' => '51988887777', 'date' => $date],
        ],
    ])->assertRedirect();

    $speaker = Speaker::query()->where('name', 'João Visitante')->first();
    $offer = $send->offers()->first();

    expect($speaker)->not->toBeNull()
        ->and($speaker->is_active)->toBeFalse()
        ->and($offer)->not->toBeNull()
        ->and($offer->status)->toBe(ExchangeOfferStatus::Draft)
        ->and($offer->target_date->toDateString())->toBe($date)
        ->and(ExchangeMessage::query()->where('invite_send_id', $send->id)->where('direction', 'inbound')->count())->toBe(1)
        ->and($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered);
});

test('a declined send no longer accepts portal submissions', function () {
    [, $team] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('c', 48),
        'status' => ExchangeInviteSendStatus::Declined,
    ]);

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'offers' => [
            ['speaker_name' => 'Maria', 'date' => $invite->month->toDateString()],
        ],
    ])->assertStatus(410);
});

test('congregations awaiting the introduction are listed when the rotation is empty', function () {
    [$user, $team] = exchangeTeam();

    $pending = Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990001',
    ]);

    Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => null,
        'secretary_phone' => null,
        'contact_email' => null,
        'secretary_email' => null,
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Exchange')
            ->has('candidates', 0)
            ->has('pendingIntro', 1)
            ->where('pendingIntro.0.id', $pending->id)
            ->where('pendingIntro.0.name', $pending->name));
});
