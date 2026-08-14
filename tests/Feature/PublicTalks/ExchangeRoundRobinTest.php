<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\ExchangeOpt;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\ExchangeRoundRobin;
use Illuminate\Support\Carbon;

/**
 * @return array{0: User, 1: Team, 2: ExchangeInvite}
 */
function roundRobinInvite(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['is_owner' => true]);

    $home = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $home->id])->save();

    $invite = ExchangeInvite::factory()->create(['team_id' => $team->id]);

    return [$user, $team->fresh(), $invite];
}

test('suggests opted-in congregations with contact, keeping unknown in the pending intro list', function () {
    [$user, $team, $invite] = roundRobinInvite();

    $optedIn = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'name' => 'Congregação Alfa',
        'contact_phone' => '51999990000',
    ]);
    $unknownOpt = Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'Congregação Beta',
        'contact_phone' => '51999990001',
    ]);
    Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'exchange_opt' => ExchangeOpt::OptedOut,
        'contact_phone' => '51999990003',
    ]);
    Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => null,
        'secretary_phone' => null,
        'contact_email' => null,
        'secretary_email' => null,
    ]);
    $team->homeCongregation->forceFill([
        'exchange_opt' => ExchangeOpt::OptedIn,
        'contact_phone' => '51999990002',
    ])->save();

    $roundRobin = app(ExchangeRoundRobin::class);

    expect($roundRobin->candidatesFor($invite)->pluck('id')->all())->toBe([$optedIn->id])
        ->and($roundRobin->pendingIntroFor($invite)->pluck('id')->all())->toContain($unknownOpt->id);
});

test('skips congregations with a live send on the invite', function () {
    [$user, $team, $invite] = roundRobinInvite();

    $busy = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);
    ExchangeInviteSend::factory()->create([
        'invite_id' => $invite->id,
        'congregation_id' => $busy->id,
        'status' => ExchangeInviteSendStatus::Sent,
    ]);

    expect(app(ExchangeRoundRobin::class)->nextFor($invite))->toBeNull();
});

test('orders candidates by least recently invited', function () {
    [$user, $team, $invite] = roundRobinInvite();

    $recent = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'name' => 'Congregação Alfa',
        'contact_phone' => '51999990000',
    ]);
    $stale = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'name' => 'Congregação Zeta',
        'contact_phone' => '51999990001',
    ]);

    $pastInvite = ExchangeInvite::factory()->create([
        'team_id' => $team->id,
        'month' => Carbon::today()->subMonth()->startOfMonth()->toDateString(),
    ]);
    ExchangeInviteSend::factory()->create([
        'invite_id' => $pastInvite->id,
        'congregation_id' => $recent->id,
        'status' => ExchangeInviteSendStatus::Declined,
        'sent_at' => Carbon::now()->subDays(2),
    ]);
    ExchangeInviteSend::factory()->create([
        'invite_id' => $pastInvite->id,
        'congregation_id' => $stale->id,
        'status' => ExchangeInviteSendStatus::Declined,
        'sent_at' => Carbon::now()->subDays(30),
    ]);

    $candidates = app(ExchangeRoundRobin::class)->candidatesFor($invite);

    expect($candidates->pluck('id')->all())->toBe([$stale->id, $recent->id]);
});
