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
use Illuminate\Support\Facades\Queue;

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
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $response = $this->actingAs($user)->from(route('public-talks.schedule', ['current_team' => $team->slug]))
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]));

    $response->assertRedirect(route('public-talks.schedule', ['current_team' => $team->slug]));

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);

    $notification = $assignment->notifications()->first();
    expect($notification)->not->toBeNull()
        ->and($notification->kind)->toBe(SpeakerNotificationKind::Assignment)
        ->and($notification->status)->toBe(SpeakerNotificationStatus::Pending)
        ->and($notification->speaker_id)->toBe($assignment->speaker_id)
        ->and($notification->sent_by_id)->toBe($user->id);
});

test('notify is blocked when speaker or outline is missing', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = TalkAssignment::factory()->create([
        'team_id' => $team->id,
        'speaker_id' => null,
        'outline_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify is blocked when the speaker has no valid phone', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->speaker->forceFill(['phone' => null])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify accepts the reminder kind', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]), ['kind' => 'reminder'])
        ->assertRedirect();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);

    expect($assignment->notifications()->first()->kind)->toBe(SpeakerNotificationKind::Reminder);
});

test('an outgoing assignment can be notified', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->forceFill(['type' => TalkAssignmentType::Outgoing])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
});

test('an invalid kind is rejected without queueing', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]), ['kind' => 'nonsense'])
        ->assertRedirect();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify is blocked when the team cannot send via the WhatsApp API', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $team->forceFill(['whatsapp_api_enabled' => false])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Queue::assertNothingPushed();
    expect($assignment->notifications()->count())->toBe(0);
});

test('notify returns 404 for an assignment of another team', function () {
    Queue::fake();

    [$user, $team] = notifyReadyTeam();
    [, $otherTeam, $otherCongregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($otherTeam, $otherCongregation);

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('an incoming assignment can be notified (visitor speaker)', function () {
    Queue::fake();

    [$user, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);
    $assignment->forceFill(['type' => TalkAssignmentType::Incoming])->save();

    $this->actingAs($user)
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertRedirect();

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);
    expect($assignment->notifications()->count())->toBe(1);
});

test('a member without the notify permission is forbidden', function () {
    Queue::fake();

    [, $team, $congregation] = notifyReadyTeam();
    $assignment = notifiableAssignment($team, $congregation);

    $member = User::factory()->create();
    $team->members()->attach($member);
    $member->assignCargo($team, DefaultCargo::Secretario->value);
    $member->switchTeam($team);

    $this->actingAs($member->fresh())
        ->post(route('public-talks.schedule.notify', ['current_team' => $team->slug, 'assignment' => $assignment->id]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
