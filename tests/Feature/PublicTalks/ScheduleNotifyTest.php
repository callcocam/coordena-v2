<?php

use App\Enums\DefaultCargo;
use App\Enums\SpeakerNotificationKind;
use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentType;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Models\User;
use App\Models\WhatsappTermsAcceptance;
use Illuminate\Support\Facades\Bus;

/**
 * @return array{0: User, 1: Team, 2: Congregation}
 */
function notifyReadyTeam(string $cargo = DefaultCargo::Coordenador->value): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['is_owner' => true]);
    $user->assignCargo($team, $cargo);
    $user->switchTeam($team);

    $congregation = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $congregation->id])->save();

    TeamWhatsappConnection::factory()->create(['team_id' => $team->id]);
    WhatsappTermsAcceptance::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);

    return [$user->fresh(), $team->fresh(), $congregation];
}

function notifiableAssignment(Team $team, Congregation $congregation): TalkAssignment
{
    return TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'speaker_id' => Speaker::factory()->create(['congregation_id' => $congregation->id])->id,
        'outline_id' => PublicTalkOutline::factory()->create()->id,
    ]);
}

test('coordinator can queue a speaker notification', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $response = $this->actingAs($user)->from(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]));

    $response->assertRedirect(route('public-talks.schedule', ['current_team' => $team->slug]));

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 1);

    $notification = $assignment->notifications()->first();
    expect($notification)->not->toBeNull()
        ->and($notification->kind)->toBe(SpeakerNotificationKind::Assignment)
        ->and($notification->status)->toBe(SpeakerNotificationStatus::Pending)
        ->and($notification->speaker_id)->toBe($assignment->speaker_id)
        ->and($notification->sent_by_id)->toBe($user->id);
});

test('notify is blocked when speaker or outline is missing', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'speaker_id' => null,
        'outline_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify is blocked when the speaker has no valid phone', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->speaker->forceFill(['phone' => null])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify accepts the reminder kind', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]), ['kind' => 'reminder'])
        ->assertRedirect();

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 1);

    expect($assignment->notifications()->first()->kind)->toBe(SpeakerNotificationKind::Reminder);
});

test('an outgoing assignment can be notified', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->forceFill(['type' => TalkAssignmentType::Outgoing])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 1);
});

test('an invalid kind is rejected without queueing', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]), ['kind' => 'nonsense'])
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify is blocked when the team cannot send via the WhatsApp API', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $team->forceFill(['whatsapp_api_enabled' => false])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify returns 404 for an assignment of another team', function () {
    Bus::fake();

    [$user, $team] = notifyReadyTeam();
    [, $otherTeam, $otherCongregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($otherTeam, $otherCongregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertNotFound();

    Bus::assertNothingDispatched();
});

test('an incoming assignment can be notified (visitor speaker)', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->forceFill(['type' => TalkAssignmentType::Incoming])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->count())->toBe(1);
});

test('a member without the notify permission is forbidden', function () {
    Bus::fake();

    [, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $member = User::factory()->create();
    $team->members()->attach($member);
    $member->assignCargo($team, DefaultCargo::Secretario->value);
    $member->switchTeam($team);

    $this->actingAs($member->fresh())
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertForbidden();

    Bus::assertNothingDispatched();
});

/**
 * @return array{0: TalkAssignment, 1: TalkAssignment}
 */
function exchangeWeekPair(Team $team, Congregation $congregation, string $date = '2026-09-12'): array
{
    $make = fn (string $type): TalkAssignment => TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'date' => $date,
        'type' => $type === 'incoming' ? TalkAssignmentType::Incoming : TalkAssignmentType::Outgoing,
        'speaker_id' => Speaker::factory()->create(['congregation_id' => $congregation->id])->id,
        'outline_id' => PublicTalkOutline::factory()->create()->id,
    ]);

    return [$make('incoming'), $make('outgoing')];
}

function notifyExchangeRoute(Team $team, TalkAssignment $assignment): string
{
    return route('public-talks.schedule.notify-exchange', [
        'current_team' => $team->slug,
        'week_start' => $assignment->week_start->toDateString(),
    ]);
}

test('notify exchange queues one notification per speaker of the week', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    [$incoming, $outgoing] = exchangeWeekPair($team, $congregation);

    $response = $this->actingAs($user)->from(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->post(notifyExchangeRoute($team, $incoming));

    $response->assertRedirect(route('public-talks.schedule', ['current_team' => $team->slug]));

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 2);

    foreach ([$incoming, $outgoing] as $assignment) {
        $notification = $assignment->notifications()->first();
        expect($notification)->not->toBeNull()
            ->and($notification->kind)->toBe(SpeakerNotificationKind::Assignment)
            ->and($notification->status)->toBe(SpeakerNotificationStatus::Pending)
            ->and($notification->speaker_id)->toBe($assignment->speaker_id)
            ->and($notification->sent_by_id)->toBe($user->id);
    }
});

test('notify exchange resolves the kind per speaker (mixed assignment and reminder)', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    [$incoming, $outgoing] = exchangeWeekPair($team, $congregation);

    $incoming->notifications()->create([
        'speaker_id' => $incoming->speaker_id,
        'kind' => SpeakerNotificationKind::Assignment,
        'status' => SpeakerNotificationStatus::Sent,
    ]);

    $this->actingAs($user)
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertRedirect();

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 2);

    expect($incoming->notifications()->latest('id')->first()->kind)->toBe(SpeakerNotificationKind::Reminder)
        ->and($outgoing->notifications()->first()->kind)->toBe(SpeakerNotificationKind::Assignment);
});

test('notify exchange skips the speaker without a valid phone', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    [$incoming, $outgoing] = exchangeWeekPair($team, $congregation);
    $outgoing->speaker->forceFill(['phone' => null])->save();

    $this->actingAs($user)
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertRedirect();

    Bus::assertDispatchedSync(SendSpeakerAssignmentNotification::class, 1);

    expect($incoming->notifications()->count())->toBe(1)
        ->and($outgoing->notifications()->count())->toBe(0);
});

test('notify exchange is blocked when no speaker is eligible', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    [$incoming, $outgoing] = exchangeWeekPair($team, $congregation);
    $incoming->speaker->forceFill(['phone' => null])->save();
    $outgoing->forceFill(['speaker_id' => null, 'outline_id' => null])->save();

    $this->actingAs($user)
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect($incoming->notifications()->count())->toBe(0);
});

test('notify exchange is blocked when the team cannot send via the WhatsApp API', function () {
    Bus::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    [$incoming] = exchangeWeekPair($team, $congregation);
    $team->forceFill(['whatsapp_api_enabled' => false])->save();

    $this->actingAs($user)
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertRedirect();

    Bus::assertNothingDispatched();
});

test('notify exchange returns 404 for a week without exchange assignments', function () {
    Bus::fake();

    [$user, $team] = notifyReadyTeam();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify-exchange', [
            'current_team' => $team->slug,
            'week_start' => '2026-09-07',
        ]))
        ->assertNotFound();

    Bus::assertNothingDispatched();
});

test('notify exchange returns 404 for a malformed week start', function () {
    Bus::fake();

    [$user, $team] = notifyReadyTeam();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify-exchange', [
            'current_team' => $team->slug,
            'week_start' => 'not-a-date',
        ]))
        ->assertNotFound();

    Bus::assertNothingDispatched();
});

test('notify exchange does not reach assignments of another team', function () {
    Bus::fake();

    [$user, $team] = notifyReadyTeam();
    [, $otherTeam, $otherCongregation] = notifyReadyTeam();
    [$incoming] = exchangeWeekPair($otherTeam, $otherCongregation);

    $this->actingAs($user)
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertNotFound();

    Bus::assertNothingDispatched();
});

test('a member without the notify permission cannot notify the exchange week', function () {
    Bus::fake();

    [, $team, $congregation] = notifyReadyTeam();
    [$incoming] = exchangeWeekPair($team, $congregation);

    $member = User::factory()->create();
    $team->members()->attach($member);
    $member->assignCargo($team, DefaultCargo::Secretario->value);
    $member->switchTeam($team);

    $this->actingAs($member->fresh())
        ->post(notifyExchangeRoute($team, $incoming))
        ->assertForbidden();

    Bus::assertNothingDispatched();
});
