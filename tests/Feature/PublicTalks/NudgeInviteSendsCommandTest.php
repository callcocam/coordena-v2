<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Jobs\SendExchangeInviteNudge;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\Team;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function unansweredSend(array $attributes = []): ExchangeInviteSend
{
    $team = Team::factory()->create([
        'home_congregation_id' => Congregation::factory()->create()->id,
    ]);

    return ExchangeInviteSend::factory()->create(array_merge([
        'invite_id' => ExchangeInvite::factory()->create(['team_id' => $team->id])->id,
        'congregation_id' => Congregation::factory()->create(['contact_phone' => '51999990000'])->id,
        'status' => ExchangeInviteSendStatus::Sent,
        'portal_token' => Str::random(48),
        'created_at' => now()->subDays((int) config('public_talks.exchange.nudge_after_days') + 1),
    ], $attributes));
}

test('nudges an unanswered send once and marks nudged_at', function () {
    Queue::fake();

    $send = unansweredSend();

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();

    Queue::assertPushed(SendExchangeInviteNudge::class, fn (SendExchangeInviteNudge $job) => $job->send->is($send));
    expect($send->fresh()->nudged_at)->not->toBeNull();

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();

    Queue::assertPushed(SendExchangeInviteNudge::class, 1);
});

test('recent or already answered sends are left alone', function () {
    Queue::fake();

    unansweredSend(['created_at' => now()->subDay()]);
    unansweredSend(['status' => ExchangeInviteSendStatus::Answered]);

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('dry-run does not dispatch nor mark nudged_at', function () {
    Queue::fake();

    $send = unansweredSend();

    $this->artisan('public-talks:nudge-pending-invite-sends', ['--dry-run' => true])->assertSuccessful();

    Queue::assertNothingPushed();
    expect($send->fresh()->nudged_at)->toBeNull();
});

test('expires a send past expire_after_days and alerts the responsible coordinator', function () {
    Queue::fake();

    $send = unansweredSend([
        'created_at' => now()->subDays((int) config('public_talks.exchange.expire_after_days') + 1),
    ]);
    Coordinator::factory()->responsible()->create([
        'team_id' => $send->invite->team_id,
        'phone' => '51988887777',
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, TemplateMessage $template): bool {
            expect($to)->toBe('5551988887777')
                ->and($template->key)->toBe('coordinator_alert');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.EXPIRE'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();

    expect($send->fresh()->status)->toBe(ExchangeInviteSendStatus::Expired);
    Queue::assertNothingPushed();

    $this->artisan('public-talks:nudge-pending-invite-sends')->assertSuccessful();
});

test('dry-run does not expire nor alert', function () {
    Queue::fake();

    $send = unansweredSend([
        'created_at' => now()->subDays((int) config('public_talks.exchange.expire_after_days') + 1),
    ]);

    $this->artisan('public-talks:nudge-pending-invite-sends', ['--dry-run' => true])->assertSuccessful();

    expect($send->fresh()->status)->toBe(ExchangeInviteSendStatus::Sent);
    Queue::assertNothingPushed();
});

test('the nudge job uses the template outside the 24h window and records the message', function () {
    $send = unansweredSend();

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, TemplateMessage $template): bool {
            expect($to)->toBe('5551999990000')
                ->and($template->key)->toBe('coordinator_alert');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.NUDGE'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    (new SendExchangeInviteNudge($send))->handle();

    $message = $send->messages()->sole();
    expect($message->direction)->toBe('outbound')
        ->and($message->wamid)->toBe('wamid.NUDGE');
});

test('the nudge job uses session text when the 24h window is open', function () {
    $send = unansweredSend();

    WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999990000',
        'wamid' => 'wamid.INBOUND',
        'type' => 'text',
        'text' => 'Oi',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')
        ->once()
        ->andReturn(SendResult::sent('cloud', 'wamid.SESSION'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    (new SendExchangeInviteNudge($send))->handle();

    expect($send->messages()->sole()->wamid)->toBe('wamid.SESSION');
});
