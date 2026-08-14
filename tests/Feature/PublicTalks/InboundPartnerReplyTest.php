<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

function partnerReplyTeam(): Team
{
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    return $team;
}

function partnerReplySend(Team $team, array $attributes = []): ExchangeInviteSend
{
    return ExchangeInviteSend::factory()->create(array_merge([
        'invite_id' => ExchangeInvite::factory()->create([
            'team_id' => $team->id,
            'month' => now()->addMonth()->startOfMonth(),
        ])->id,
        'congregation_id' => Congregation::factory()->create([
            'name' => 'Cong. Leste',
            'contact_phone' => '51977776666',
        ])->id,
        'status' => ExchangeInviteSendStatus::Sent,
        'sent_at' => now()->subDay(),
    ], $attributes));
}

function partnerReplyInbound(?string $text, string $type = 'text'): WhatsAppInboundMessage
{
    return WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.PARTNER',
        'type' => $type,
        'text' => $text,
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);
}

test('resposta da parceira vira mensagem íntegra na mesa e o send passa a respondido', function () {
    $team = partnerReplyTeam();
    $send = partnerReplySend($team);
    $body = "Podemos as semanas 1 e 3.\nOradores: João (esboço 12) e Pedro (esboço 45).";

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, $template) use ($team, $send) {
            $link = route('public-talks.exchange.sends.show', ['current_team' => $team, 'send' => $send]);

            expect($to)->toBe('5551999990000')
                ->and($template->params['summary'])->toContain('Cong. Leste')
                ->and($template->params['summary'])->toContain($link)
                ->and($template->params['summary'])->not->toContain("\n");

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = partnerReplyInbound($body);

    app(InboundDispatcher::class)->dispatch($message);

    $send->refresh();
    $stored = $send->messages()->first();

    expect($stored->body)->toBe($body)
        ->and($stored->direction)->toBe('inbound')
        ->and($stored->channel)->toBe('whatsapp')
        ->and($stored->wamid)->toBe('wamid.PARTNER')
        ->and($send->status)->toBe(ExchangeInviteSendStatus::Answered)
        ->and($send->answered_at)->not->toBeNull()
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED);
});

test('nova resposta num send já respondido só acrescenta mensagem, sem mexer no answered_at', function () {
    $team = partnerReplyTeam();
    $answeredAt = now()->subHours(3);
    $send = partnerReplySend($team, [
        'status' => ExchangeInviteSendStatus::Answered,
        'answered_at' => $answeredAt,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch(partnerReplyInbound('Mais uma semana: dia 22.'));

    $send->refresh();

    expect($send->messages()->count())->toBe(1)
        ->and($send->status)->toBe(ExchangeInviteSendStatus::Answered)
        ->and($send->answered_at->timestamp)->toBe($answeredAt->timestamp);
});

test('mensagem sem texto vira placeholder com o tipo, nunca corpo vazio', function () {
    $team = partnerReplyTeam();
    $send = partnerReplySend($team);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch(partnerReplyInbound(null, 'image'));

    expect($send->messages()->first()->body)->toBe('[image]');
});
