<?php

use App\Enums\DefaultCargo;
use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOfferStatus;
use App\Models\Congregation;
use App\Models\CongregationIntro;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use App\Models\ExchangeOffer;
use App\Models\PublicTalkOutline;
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

test('the portal exposes the registered partner speakers and the outline catalog', function () {
    [, $team] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('e', 48),
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    $outline = PublicTalkOutline::factory()->create(['number' => 42]);
    $partner = Speaker::factory()->create([
        'congregation_id' => $send->congregation_id,
        'name' => 'Orador Parceiro',
        'is_active' => false,
    ]);
    $partner->outlines()->attach($outline->id);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('partnerSpeakers', 1)
            ->where('partnerSpeakers.0.id', $partner->id)
            ->where('partnerSpeakers.0.name', 'Orador Parceiro')
            ->where('partnerSpeakers.0.outline_ids', [$outline->id])
            ->where('outlineCatalog', fn ($catalog) => collect($catalog)->contains(fn ($item) => $item['id'] === $outline->id && $item['number'] === 42)));
});

test('the portal exposes the arrangement from the invited congregation point of view', function () {
    [, $team, $congregation] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('f', 48),
        'status' => ExchangeInviteSendStatus::Accepted,
        'accepted_at' => now(),
    ]);

    $outline = PublicTalkOutline::factory()->create(['number' => 7, 'title' => 'Tema da Troca']);
    $visitingSpeaker = Speaker::factory()->create([
        'congregation_id' => $send->congregation_id,
        'name' => 'Orador Visitante',
    ]);

    ExchangeOffer::factory()->create([
        'invite_send_id' => $send->id,
        'direction' => 'incoming',
        'speaker_id' => $visitingSpeaker->id,
        'target_date' => $invite->month->copy()->addDays(9)->toDateString(),
        'chosen_outline_id' => $outline->id,
        'status' => ExchangeOfferStatus::Confirmed,
    ]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('arrangement', 1)
            ->where('arrangement.0.direction', 'outgoing')
            ->where('arrangement.0.speaker_name', 'Orador Visitante')
            ->where('arrangement.0.week', $invite->month->copy()->addDays(9)->toDateString())
            ->where('arrangement.0.outline.number', 7)
            ->where('arrangement.0.outline.title', 'Tema da Troca')
            ->where('arrangement.0.status', 'confirmed'));
});

test('the portal lists the outlines the invited congregation received in the last six months', function () {
    [, $team, $congregation] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('g', 48),
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    $outline = PublicTalkOutline::factory()->create(['number' => 21, 'title' => 'Tema Recente']);
    $homeSpeaker = Speaker::factory()->create(['congregation_id' => $congregation->id, 'name' => 'Orador da Casa']);

    TalkAssignment::factory()->outgoing()->create([
        'team_id' => $team->id,
        'counterpart_congregation_id' => $send->congregation_id,
        'outline_id' => $outline->id,
        'speaker_id' => $homeSpeaker->id,
        'date' => now()->subMonths(2)->toDateString(),
    ]);

    TalkAssignment::factory()->outgoing()->create([
        'team_id' => $team->id,
        'counterpart_congregation_id' => $send->congregation_id,
        'outline_id' => PublicTalkOutline::factory()->create()->id,
        'date' => now()->subMonths(8)->toDateString(),
    ]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->count('recentOutlines', 1)
            ->where('recentOutlines.0.outline.number', 21)
            ->where('recentOutlines.0.outline.title', 'Tema Recente')
            ->where('recentOutlines.0.speaker_name', 'Orador da Casa')
            ->where('recentOutlines.0.date', now()->subMonths(2)->toDateString()));
});

test('the portal exposes the expiration date for a sent send and the help url', function () {
    [, $team] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('h', 48),
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    $expected = $send->created_at
        ->copy()
        ->addDays((int) config('public_talks.exchange.expire_after_days'))
        ->toDateString();

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('expiresAt', $expected)
            ->where('helpUrl', route('help.public-talks')));

    $send->update(['status' => ExchangeInviteSendStatus::Accepted, 'accepted_at' => now()]);

    $this->get(route('exchange.portal', ['portal_token' => $send->portal_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('expiresAt', null));
});

test('the public help page renders without authentication', function () {
    $this->get(route('help.public-talks'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('help/PublicTalks'));
});

test('the portal returns 404 for an unknown token', function () {
    $this->get(route('exchange.portal', ['portal_token' => str_repeat('x', 48)]))
        ->assertNotFound();
});

/**
 * Portal context for submission tests: one open home week in the invite
 * month, one exposed home speaker offering one outline, an accepted send.
 *
 * @return array{0: ExchangeInviteSend, 1: TalkAssignment, 2: Speaker, 3: PublicTalkOutline, 4: string}
 */
function exchangePortalContext(): array
{
    [, $team, $congregation] = exchangeTeam();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);
    $openWeek = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $invite->month->copy()->addDays(9)->toDateString(),
    ]);

    $homeSpeaker = Speaker::factory()->create(['congregation_id' => $congregation->id, 'name' => 'Orador Local']);
    $outline = PublicTalkOutline::factory()->create();
    $homeSpeaker->outlines()->attach($outline->id);

    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'portal_token' => str_repeat('b', 48),
        'status' => ExchangeInviteSendStatus::Accepted,
        'accepted_at' => now(),
    ]);

    $monthStart = $invite->month->copy()->startOfMonth();
    $outgoingMonday = $monthStart->copy()->startOfWeek(Carbon::MONDAY);

    if ($outgoingMonday->lt($monthStart)) {
        $outgoingMonday = $outgoingMonday->addWeek();
    }

    return [$send, $openWeek, $homeSpeaker, $outline, $outgoingMonday->toDateString()];
}

test('an incoming submission stores a draft offer with the speaker outline list and marks the send answered', function () {
    [$send, $openWeek] = exchangePortalContext();

    $outlines = PublicTalkOutline::factory()->count(2)->sequence(['number' => 12], ['number' => 34])->create();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            [
                'week' => $openWeek->date->toDateString(),
                'speaker_name' => 'João Visitante',
                'phone' => '51988887777',
                'outline_ids' => [$outlines[0]->id, $outlines[1]->id],
            ],
        ],
    ])->assertRedirect();

    $speaker = Speaker::query()->where('name', 'João Visitante')->first();
    $offer = $send->offers()->where('direction', 'incoming')->first();

    expect($speaker)->not->toBeNull()
        ->and($speaker->is_active)->toBeFalse()
        ->and($speaker->congregation_id)->toBe($send->congregation_id)
        ->and($offer)->not->toBeNull()
        ->and($offer->status)->toBe(ExchangeOfferStatus::Draft)
        ->and($offer->speaker_id)->toBe($speaker->id)
        ->and($offer->target_date->toDateString())->toBe($openWeek->date->toDateString())
        ->and($offer->outlines->pluck('number')->sort()->values()->all())->toBe([$outlines[0]->number, $outlines[1]->number])
        ->and(ExchangeMessage::query()->where('invite_send_id', $send->id)->where('direction', 'inbound')->count())->toBe(1)
        ->and($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered);
});

test('an incoming submission can reference a registered partner speaker instead of typing a name', function () {
    [$send, $openWeek] = exchangePortalContext();

    $partner = Speaker::factory()->create([
        'congregation_id' => $send->congregation_id,
        'name' => 'Orador Conhecido',
        'is_active' => false,
        'phone' => null,
    ]);

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            [
                'week' => $openWeek->date->toDateString(),
                'speaker_id' => $partner->id,
                'phone' => '51977776666',
            ],
        ],
    ])->assertRedirect();

    $offer = $send->offers()->where('direction', 'incoming')->first();

    expect(Speaker::query()->where('congregation_id', $send->congregation_id)->count())->toBe(1)
        ->and($offer->speaker_id)->toBe($partner->id)
        ->and($partner->refresh()->phone)->toBe('5551977776666');
});

test('an incoming submission with an unknown partner speaker id is rejected', function () {
    [$send, $openWeek] = exchangePortalContext();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            ['week' => $openWeek->date->toDateString(), 'speaker_id' => str_repeat('9', 36)],
        ],
    ])->assertSessionHasErrors(['incoming.0.speaker_id']);
});

test('an incoming submission with an outline id outside the catalog is rejected', function () {
    [$send, $openWeek] = exchangePortalContext();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            [
                'week' => $openWeek->date->toDateString(),
                'speaker_name' => 'João Visitante',
                'outline_ids' => [str_repeat('9', 36)],
            ],
        ],
    ])->assertSessionHasErrors(['incoming.0.outline_ids']);
});

test('an outgoing submission stores a draft offer with the single outline the partner chose', function () {
    [$send, , $homeSpeaker, $outline, $outgoingWeek] = exchangePortalContext();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $outline->id],
        ],
    ])->assertRedirect();

    $offer = $send->offers()->where('direction', 'outgoing')->first();

    expect($offer)->not->toBeNull()
        ->and($offer->speaker_id)->toBe($homeSpeaker->id)
        ->and($offer->target_date->toDateString())->toBe($outgoingWeek)
        ->and($offer->outlines->pluck('id')->all())->toBe([$outline->id])
        ->and($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered);
});

test('a mixed submission stores incoming and outgoing offers in a single reply', function () {
    [$send, $openWeek, $homeSpeaker, $outline, $outgoingWeek] = exchangePortalContext();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            ['week' => $openWeek->date->toDateString(), 'speaker_name' => 'Maria Visitante'],
        ],
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $outline->id],
        ],
    ])->assertRedirect();

    expect($send->offers()->where('direction', 'incoming')->count())->toBe(1)
        ->and($send->offers()->where('direction', 'outgoing')->count())->toBe(1)
        ->and(ExchangeMessage::query()->where('invite_send_id', $send->id)->where('direction', 'inbound')->count())->toBe(1);
});

test('an incoming week outside the open weeks is rejected', function () {
    [$send, $openWeek] = exchangePortalContext();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            ['week' => $openWeek->date->copy()->addYear()->toDateString(), 'speaker_name' => 'João Visitante'],
        ],
    ])->assertSessionHasErrors(['incoming.0.week']);

    expect($send->offers()->count())->toBe(0);
});

test('an outgoing outline the speaker does not offer is rejected', function () {
    [$send, , $homeSpeaker, , $outgoingWeek] = exchangePortalContext();

    $otherOutline = PublicTalkOutline::factory()->create();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $otherOutline->id],
        ],
    ])->assertSessionHasErrors(['outgoing.0.outline_id']);

    expect($send->offers()->count())->toBe(0);
});

test('an outgoing speaker that is not exposed is rejected', function () {
    [$send, , , $outline, $outgoingWeek] = exchangePortalContext();

    $stranger = Speaker::factory()->create();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $stranger->id, 'outline_id' => $outline->id],
        ],
    ])->assertSessionHasErrors(['outgoing.0.speaker_id']);
});

test('an incoming submission repeating the same speaker in two weeks is rejected', function () {
    [$send, $openWeek] = exchangePortalContext();

    $secondWeek = TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $send->invite->month->copy()->addDays(16)->toDateString(),
    ]);

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            ['week' => $openWeek->date->toDateString(), 'speaker_name' => 'João Visitante'],
            ['week' => $secondWeek->date->toDateString(), 'speaker_name' => 'joão visitante'],
        ],
    ])->assertSessionHasErrors(['incoming.1.speaker_name']);

    expect($send->offers()->count())->toBe(0);
});

test('an incoming submission repeating the same registered speaker id is rejected', function () {
    [$send, $openWeek] = exchangePortalContext();

    $secondWeek = TalkAssignment::factory()->create([
        'team_id' => $send->invite->team_id,
        'date' => $send->invite->month->copy()->addDays(16)->toDateString(),
    ]);

    $partner = Speaker::factory()->create([
        'congregation_id' => $send->congregation_id,
        'is_active' => false,
    ]);

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'incoming' => [
            ['week' => $openWeek->date->toDateString(), 'speaker_id' => $partner->id],
            ['week' => $secondWeek->date->toDateString(), 'speaker_id' => $partner->id],
        ],
    ])->assertSessionHasErrors(['incoming.1.speaker_id']);

    expect($send->offers()->count())->toBe(0);
});

test('an outgoing submission repeating the same home speaker is rejected', function () {
    [$send, , $homeSpeaker, $outline, $outgoingWeek] = exchangePortalContext();

    $secondWeek = Carbon::parse($outgoingWeek)->addWeek()->toDateString();

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $outline->id],
            ['week' => $secondWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $outline->id],
        ],
    ])->assertSessionHasErrors(['outgoing.1.speaker_id']);

    expect($send->offers()->count())->toBe(0);
});

test('an outgoing submission repeating the same week is rejected', function () {
    [$send, , $homeSpeaker, $outline, $outgoingWeek] = exchangePortalContext();

    $secondSpeaker = Speaker::factory()->create([
        'congregation_id' => $homeSpeaker->congregation_id,
        'name' => 'Outro Orador Local',
    ]);
    $secondSpeaker->outlines()->attach($outline->id);

    $this->post(route('exchange.portal.submit', ['portal_token' => $send->portal_token]), [
        'outgoing' => [
            ['week' => $outgoingWeek, 'speaker_id' => $homeSpeaker->id, 'outline_id' => $outline->id],
            ['week' => $outgoingWeek, 'speaker_id' => $secondSpeaker->id, 'outline_id' => $outline->id],
        ],
    ])->assertSessionHasErrors(['outgoing.1.week']);

    expect($send->offers()->count())->toBe(0);
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
        'incoming' => [
            ['week' => $invite->month->toDateString(), 'speaker_name' => 'Maria'],
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
            ->where('pendingIntro.0.name', $pending->name)
            ->where('pendingIntro.0.has_whatsapp', true)
            ->where('pendingIntro.0.intro_status', null));
});

test('pending intro items expose the latest introduction status', function () {
    [$user, $team] = exchangeTeam();

    $pending = Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990001',
    ]);

    CongregationIntro::factory()->sent()->create([
        'team_id' => $team->id,
        'congregation_id' => $pending->id,
    ]);

    $this->actingAs($user)
        ->get(route('public-talks.exchange.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Exchange')
            ->has('pendingIntro', 1)
            ->where('pendingIntro.0.id', $pending->id)
            ->where('pendingIntro.0.intro_status', 'sent'));
});
