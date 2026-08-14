<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Jobs\SendExchangeInvite;
use App\Models\Congregation;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Models\User;
use App\Models\WhatsappTermsAcceptance;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Team, 2: Congregation}
 */
function exchangeWhatsappTeam(): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $home = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $home->id])->save();

    TeamWhatsappConnection::factory()->create(['team_id' => $team->id]);
    WhatsappTermsAcceptance::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);

    $partner = Congregation::factory()->create([
        'owner_user_id' => $user->id,
        'contact_phone' => '51999990000',
    ]);

    $user->unsetRelation('currentTeam');

    return [$user, $team->fresh(), $partner];
}

function pendingWhatsappSend(Team $team, Congregation $partner): ExchangeInviteSend
{
    return ExchangeInviteSend::factory()->create([
        'invite_id' => ExchangeInvite::factory()->create(['team_id' => $team->id])->id,
        'congregation_id' => $partner->id,
        'portal_token' => Str::random(48),
    ]);
}

test('whatsapp send is queued as pending', function () {
    Queue::fake();

    [$user, $team, $partner] = exchangeWhatsappTeam();

    $response = $this->actingAs($user)->post(route('public-talks.exchange.sends.store', ['current_team' => $team->slug]), [
        'month' => now()->addMonth()->format('Y-m'),
        'channel' => 'whatsapp',
        'congregation_id' => $partner->id,
    ]);

    $response->assertRedirect();

    $send = ExchangeInviteSend::query()->sole();
    expect($send->channel)->toBe('whatsapp')
        ->and($send->status)->toBe(ExchangeInviteSendStatus::Pending)
        ->and($send->sent_at)->toBeNull()
        ->and($send->portal_token)->not->toBeNull();

    Queue::assertPushed(SendExchangeInvite::class, fn (SendExchangeInvite $job) => $job->send->is($send));
});

test('whatsapp channel is blocked when the team cannot send via the api', function () {
    Queue::fake();

    [$user, $team, $partner] = exchangeWhatsappTeam();
    $team->forceFill(['whatsapp_api_enabled' => false])->save();

    $response = $this->actingAs($user)->post(route('public-talks.exchange.sends.store', ['current_team' => $team->slug]), [
        'month' => now()->addMonth()->format('Y-m'),
        'channel' => 'whatsapp',
        'congregation_id' => $partner->id,
    ]);

    $response->assertSessionHasErrors('channel');
    expect(ExchangeInviteSend::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('whatsapp channel is blocked when the congregation has no valid phone', function () {
    Queue::fake();

    [$user, $team, $partner] = exchangeWhatsappTeam();
    $partner->forceFill(['contact_phone' => null])->save();

    $response = $this->actingAs($user)->post(route('public-talks.exchange.sends.store', ['current_team' => $team->slug]), [
        'month' => now()->addMonth()->format('Y-m'),
        'channel' => 'whatsapp',
        'congregation_id' => $partner->id,
    ]);

    $response->assertSessionHasErrors('congregation_id');
    expect(ExchangeInviteSend::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the job delivers the template and marks the send as sent', function () {
    [, $team, $partner] = exchangeWhatsappTeam();
    $send = pendingWhatsappSend($team, $partner);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, TemplateMessage $template) use ($send) {
            expect($to)->toBe('5551999990000')
                ->and($template->key)->toBe('exchange_invite')
                ->and($template->params['link'])->toContain($send->portal_token);

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.EXCHANGE'));
    $client->shouldNotReceive('sendSessionText');
    WhatsApp::shouldReceive('for')->andReturn($client);

    SendExchangeInvite::dispatchSync($send);

    $send->refresh();
    expect($send->status)->toBe(ExchangeInviteSendStatus::Sent)
        ->and($send->sent_at)->not->toBeNull()
        ->and($send->messages()->sole()->wamid)->toBe('wamid.EXCHANGE');
});

test('the job also sends the rich text when the 24h window is open', function () {
    [, $team, $partner] = exchangeWhatsappTeam();
    $send = pendingWhatsappSend($team, $partner);

    WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999990000',
        'wamid' => 'wamid.INBOUND',
        'type' => 'text',
        'text' => 'Oi',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.EXCHANGE'));
    $client->shouldReceive('sendSessionText')
        ->once()
        ->withArgs(fn (string $to, string $body) => $to === '5551999990000' && $body !== '')
        ->andReturn(SendResult::sent('cloud', 'wamid.SESSION'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    SendExchangeInvite::dispatchSync($send);

    expect($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Sent);
});

test('a terminal meta error marks the send as failed', function () {
    [, $team, $partner] = exchangeWhatsappTeam();
    $send = pendingWhatsappSend($team, $partner);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->andThrow(new CloudApiException('Re-engagement required.', 131047));
    WhatsApp::shouldReceive('for')->andReturn($client);

    SendExchangeInvite::dispatchSync($send);

    $send->refresh();
    expect($send->status)->toBe(ExchangeInviteSendStatus::Failed)
        ->and($send->messages()->count())->toBe(0);
});

test('the job fails the send when the congregation phone is invalid', function () {
    [, $team, $partner] = exchangeWhatsappTeam();
    $partner->forceFill(['contact_phone' => 'sem-telefone'])->save();
    $send = pendingWhatsappSend($team, $partner);

    WhatsApp::shouldReceive('for')->never();

    SendExchangeInvite::dispatchSync($send);

    expect($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Failed);
});

test('the job skips sends that are no longer pending', function () {
    [, $team, $partner] = exchangeWhatsappTeam();
    $send = pendingWhatsappSend($team, $partner);
    $send->update(['status' => ExchangeInviteSendStatus::Sent]);

    WhatsApp::shouldReceive('for')->never();

    SendExchangeInvite::dispatchSync($send);

    expect($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Sent);
});
