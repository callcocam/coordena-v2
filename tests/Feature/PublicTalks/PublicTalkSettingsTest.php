<?php

use App\Enums\DefaultCargo;
use App\Enums\ExchangeInviteSendStatus;
use App\Enums\TalkAssignmentStatus;
use App\Jobs\SendExchangeInviteNudge;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\PublicTalkSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Team}
 */
function settingsUserWithTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'home_congregation_id' => Congregation::factory()->create()->id,
    ]);

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    Coordinator::factory()->responsible()->create(['team_id' => $team->id]);

    return [$user->fresh(), $team->fresh()];
}

function settingsUrl(Team $team): string
{
    return route('public-talks.settings.show', ['current_team' => $team->slug]);
}

test('the effective values fall back to the config defaults', function () {
    $team = Team::factory()->create();

    $settings = app(PublicTalkSettings::class)->for($team);

    expect($settings->all())->toBe([
        'speaker_reminder_days' => 3,
        'speaker_second_reminder_days' => 1,
        'pending_alert_days' => 0,
        'exchange_nudge_days' => 4,
        'exchange_expire_days' => 10,
    ])->and($settings->overrides())->toBe([]);
});

test('a team override wins over the default and save drops values equal to it', function () {
    $team = Team::factory()->create();
    $settings = app(PublicTalkSettings::class)->for($team);

    $settings->save([
        'speaker_reminder_days' => 5,
        'speaker_second_reminder_days' => 1, // equal to the default → dropped
        'exchange_nudge_days' => null,
    ]);

    $team->refresh();

    expect($team->public_talk_settings)->toBe(['speaker_reminder_days' => 5])
        ->and($settings->get('speaker_reminder_days'))->toBe(5)
        ->and($settings->get('speaker_second_reminder_days'))->toBe(1);

    $settings->save(['speaker_reminder_days' => null]);

    expect($team->refresh()->public_talk_settings)->toBeNull();
});

test('the settings page shows effective values, defaults and overrides', function () {
    [$user, $team] = settingsUserWithTeam();

    $team->forceFill(['public_talk_settings' => ['exchange_nudge_days' => 6]])->save();

    $this->actingAs($user)
        ->get(settingsUrl($team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('publicTalks/Settings')
            ->where('settings.exchange_nudge_days', 6)
            ->where('settings.speaker_reminder_days', 3)
            ->where('defaults.exchange_nudge_days', 4)
            ->where('overrides.exchange_nudge_days', 6)
            ->where('canManage', true));
});

test('publicador cannot open nor update the settings', function () {
    [$user, $team] = settingsUserWithTeam(DefaultCargo::Publicador->value);

    $this->actingAs($user)->get(settingsUrl($team))->assertForbidden();

    $this->actingAs($user)
        ->put(route('public-talks.settings.update', ['current_team' => $team->slug]), [
            'speaker_reminder_days' => 5,
        ])
        ->assertForbidden();
});

test('updating persists only the customized values and empty resets to default', function () {
    [$user, $team] = settingsUserWithTeam();

    $this->actingAs($user)
        ->from(settingsUrl($team))
        ->put(route('public-talks.settings.update', ['current_team' => $team->slug]), [
            'speaker_reminder_days' => 5,
            'speaker_second_reminder_days' => null,
            'pending_alert_days' => 0, // equal to the default → dropped
            'exchange_nudge_days' => 6,
            'exchange_expire_days' => 15,
        ])
        ->assertRedirect(settingsUrl($team));

    expect($team->refresh()->public_talk_settings)->toBe([
        'speaker_reminder_days' => 5,
        'exchange_nudge_days' => 6,
        'exchange_expire_days' => 15,
    ]);

    $this->actingAs($user)
        ->from(settingsUrl($team))
        ->put(route('public-talks.settings.update', ['current_team' => $team->slug]), [])
        ->assertRedirect(settingsUrl($team));

    expect($team->refresh()->public_talk_settings)->toBeNull();
});

test('the cross-field rules reject an incoherent pair of values', function () {
    [$user, $team] = settingsUserWithTeam();

    $this->actingAs($user)
        ->from(settingsUrl($team))
        ->put(route('public-talks.settings.update', ['current_team' => $team->slug]), [
            'speaker_reminder_days' => 2,
            'speaker_second_reminder_days' => 2,
            'exchange_nudge_days' => 10,
            'exchange_expire_days' => 10,
        ])
        ->assertSessionHasErrors(['speaker_second_reminder_days', 'exchange_expire_days']);

    expect($team->refresh()->public_talk_settings)->toBeNull();
});

test('speaker reminders honor the team reminder override', function () {
    Queue::fake();

    [, $team] = settingsUserWithTeam();
    $team->forceFill(['public_talk_settings' => ['speaker_reminder_days' => 5]])->save();

    TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => Carbon::today()->addDays(5)->toDateString(),
        'speaker_id' => Speaker::factory()->create([
            'congregation_id' => $team->home_congregation_id,
        ])->id,
        'outline_id' => PublicTalkOutline::factory()->create()->id,
        'status' => TalkAssignmentStatus::Scheduled,
    ]);

    $this->artisan('public-talks:send-speaker-reminders')->assertSuccessful();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
});

test('the invite nudge honors the team exchange override', function () {
    Queue::fake();

    $team = Team::factory()->create([
        'home_congregation_id' => Congregation::factory()->create()->id,
        'public_talk_settings' => ['exchange_nudge_days' => 2],
    ]);

    $send = ExchangeInviteSend::factory()->create([
        'invite_id' => ExchangeInvite::factory()->create(['team_id' => $team->id])->id,
        'congregation_id' => Congregation::factory()->create(['contact_phone' => '51999990000'])->id,
        'status' => ExchangeInviteSendStatus::Sent,
        'portal_token' => Str::random(48),
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();

    Queue::assertPushed(SendExchangeInviteNudge::class, fn (SendExchangeInviteNudge $job) => $job->send->is($send));
    expect($send->fresh()->nudged_at)->not->toBeNull();
});
