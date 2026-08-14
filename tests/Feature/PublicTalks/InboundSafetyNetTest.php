<?php

use App\Models\Coordinator;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Models\User;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

function safetyNetTeam(): Team
{
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);
    TeamWhatsappConnection::factory()->create([
        'team_id' => $team->id,
        'phone_number_id' => '111222333444555',
    ]);

    return $team;
}

function safetyNetInbound(array $attributes = []): WhatsAppInboundMessage
{
    return WhatsAppInboundMessage::query()->create(array_merge([
        'wa_id' => '5551900001111',
        'wamid' => 'wamid.UNKNOWN.'.fake()->unique()->lexify('??????'),
        'phone_number_id' => '111222333444555',
        'contact_name' => 'Fulano',
        'type' => 'text',
        'text' => 'Bom dia, é da pizzaria?',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ], $attributes));
}

test('mensagem não reconhecida vai ao responsável do time dono do número', function () {
    safetyNetTeam();

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, $template) {
            expect($to)->toBe('5551999990000')
                ->and($template->params['summary'])->toContain('Fulano')
                ->and($template->params['summary'])->toContain('pizzaria');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = safetyNetInbound();

    app(InboundDispatcher::class)->dispatch($message);

    expect($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED)
        ->and($message->forwarded_to)->toBe('5551999990000');
});

test('remetente insistente é encaminhado uma vez só dentro da janela de throttle', function () {
    safetyNetTeam();

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $first = safetyNetInbound();
    $second = safetyNetInbound(['text' => 'Alô?!']);

    app(InboundDispatcher::class)->dispatch($first);
    app(InboundDispatcher::class)->dispatch($second);

    expect($first->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED)
        ->and($second->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED);
});

test('sem conexão que identifique o time, a mensagem fica só registrada como não tratada', function () {
    safetyNetTeam();
    WhatsApp::shouldReceive('for')->never();

    $message = safetyNetInbound(['phone_number_id' => '999888777666555']);

    app(InboundDispatcher::class)->dispatch($message);

    expect($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED);
});
