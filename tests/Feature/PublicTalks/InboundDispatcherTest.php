<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Enums\SpeakerNotificationStatus;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use App\Models\TalkAssignmentNotification;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\Inbound\CoordinatorConversationHandler;
use App\Services\PublicTalks\Inbound\ExchangeInviteButtonHandler;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use App\Services\PublicTalks\Inbound\IntroButtonHandler;
use App\Services\PublicTalks\Inbound\PartnerReplyHandler;
use App\Services\PublicTalks\Inbound\ReactivationHandler;
use App\Services\PublicTalks\Inbound\SafetyNetHandler;
use App\Services\PublicTalks\Inbound\SpeakerButtonHandler;
use App\Services\PublicTalks\Inbound\SpeakerFreeTextHandler;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Events\WhatsAppMessageReceived;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

function dispatcherTeam(): Team
{
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    return $team;
}

function dispatcherPartnerSend(Team $team, string $contactPhone): ExchangeInviteSend
{
    return ExchangeInviteSend::factory()->create([
        'invite_id' => ExchangeInvite::factory()->create(['team_id' => $team->id])->id,
        'congregation_id' => Congregation::factory()->create(['contact_phone' => $contactPhone])->id,
        'status' => ExchangeInviteSendStatus::Sent,
        'sent_at' => now()->subDay(),
    ]);
}

function dispatcherInbound(array $attributes = []): WhatsAppInboundMessage
{
    return WhatsAppInboundMessage::query()->create(array_merge([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.IN.'.fake()->unique()->lexify('??????'),
        'type' => 'text',
        'text' => 'Olá!',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ], $attributes));
}

test('a cadeia de precedência é um contrato único e ordenado', function () {
    expect(InboundDispatcher::HANDLERS)->toBe([
        SpeakerButtonHandler::class,
        IntroButtonHandler::class,
        ExchangeInviteButtonHandler::class,
        CoordinatorConversationHandler::class,
        PartnerReplyHandler::class,
        SpeakerFreeTextHandler::class,
        ReactivationHandler::class,
        SafetyNetHandler::class,
    ]);
});

test('o mesmo wamid nunca é processado duas vezes', function () {
    $team = dispatcherTeam();
    dispatcherPartnerSend($team, '51977776666');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = dispatcherInbound(['text' => 'Podemos ajudar!']);

    app(InboundDispatcher::class)->dispatch($message);
    app(InboundDispatcher::class)->dispatch($message->fresh());

    expect(ExchangeMessage::query()->count())->toBe(1);
});

test('rótulo de botão sem correlação por wamid não confirma notificação nenhuma', function () {
    $team = dispatcherTeam();
    dispatcherPartnerSend($team, '51977776666');
    $notification = TalkAssignmentNotification::factory()->sent()->create(['wamid' => 'wamid.OUT']);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    // "Tudo certo" vindo do contato da parceira, SEM context.id de notificação.
    $message = dispatcherInbound([
        'type' => 'button',
        'text' => 'Tudo certo',
        'payload' => ['button' => ['text' => 'Tudo certo']],
    ]);

    app(InboundDispatcher::class)->dispatch($message);

    expect($notification->refresh()->status)->toBe(SpeakerNotificationStatus::Sent)
        ->and(ExchangeMessage::query()->count())->toBe(1);
});

test('mensagem que ninguém reconhece fica marcada como não tratada', function () {
    dispatcherTeam();
    WhatsApp::shouldReceive('for')->never();

    $message = dispatcherInbound(['wa_id' => '5551900000000']);

    app(InboundDispatcher::class)->dispatch($message);

    expect($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED)
        ->and($message->handled_at)->not->toBeNull();
});

test('o evento do webhook chega ao dispatcher pelo listener', function () {
    dispatcherTeam();
    WhatsApp::shouldReceive('for')->never();

    event(new WhatsAppMessageReceived(
        message: [
            'id' => 'wamid.EVENT',
            'from' => '5551900000000',
            'type' => 'text',
            'text' => ['body' => 'Oi, tudo bem?'],
        ],
        value: [],
        phoneNumberId: null,
    ));

    $stored = WhatsAppInboundMessage::query()->where('wamid', 'wamid.EVENT')->first();

    expect($stored)->not->toBeNull()
        ->and($stored->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED);
});
