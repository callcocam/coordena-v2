<?php

use App\Enums\ExchangeInviteSendStatus;
use App\Jobs\SendExchangeInvite;
use App\Models\Congregation;
use App\Models\Coordinator;
use App\Models\ExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Team, 2: Congregation}
 */
function inviteButtonTeam(): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $home = Congregation::factory()->create(['owner_user_id' => $user->id]);
    $team->forceFill(['home_congregation_id' => $home->id])->save();

    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    $partner = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'name' => 'Cong. Leste',
        'contact_phone' => '51977776666',
    ]);

    $user->unsetRelation('currentTeam');

    return [$user, $team->fresh(), $partner];
}

function inviteButtonSend(Team $team, Congregation $partner, array $attributes = []): ExchangeInviteSend
{
    $send = ExchangeInviteSend::factory()->create(array_merge([
        'invite_id' => ExchangeInvite::factory()->create([
            'team_id' => $team->id,
            'month' => now()->addMonth()->startOfMonth(),
        ])->id,
        'congregation_id' => $partner->id,
        'portal_token' => Str::random(48),
        'status' => ExchangeInviteSendStatus::Sent,
        'sent_at' => now()->subHour(),
    ], $attributes));

    $send->messages()->create([
        'direction' => 'outbound',
        'channel' => 'whatsapp',
        'body' => 'convite',
        'wamid' => 'wamid.OPENER',
    ]);

    return $send;
}

function inviteButtonReply(string $label): WhatsAppInboundMessage
{
    return WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.REPLY',
        'type' => 'button',
        'text' => $label,
        'context_id' => 'wamid.OPENER',
        'payload' => ['button' => ['text' => $label, 'payload' => $label]],
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);
}

test('"Sim, vamos combinar" aceita o convite e só então a sessão revela oradores livres e o portal', function () {
    [, $team, $partner] = inviteButtonTeam();

    $free = Speaker::factory()->create(['congregation_id' => $team->home_congregation_id, 'name' => 'João Livre']);
    $busy = Speaker::factory()->create(['congregation_id' => $team->home_congregation_id, 'name' => 'Beto Ocupado']);
    TalkAssignment::factory()->outgoing()->confirmed()->for($team)->create([
        'speaker_id' => $busy->id,
        'date' => now()->addMonth()->startOfMonth()->addDays(10),
    ]);

    $send = inviteButtonSend($team, $partner);
    $message = inviteButtonReply('Sim, vamos combinar');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')
        ->once()
        ->withArgs(function (string $to, string $text) use ($send, $free, $busy) {
            expect($to)->toBe('5551977776666')
                ->and($text)->toContain($send->portal_token)
                ->and($text)->toContain($free->name)
                ->and($text)->not->toContain($busy->name);

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.SESSION'));
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, $template) {
            expect($to)->toBe('5551999990000')
                ->and($template->params['summary'])->toContain('Cong. Leste');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    $send->refresh();
    expect($send->status)->toBe(ExchangeInviteSendStatus::Accepted)
        ->and($send->accepted_at)->not->toBeNull()
        ->and($send->answered_at)->not->toBeNull()
        ->and($send->messages()->where('direction', 'inbound')->sole()->wamid)->toBe('wamid.REPLY')
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED)
        ->and($message->forwarded_to)->toBe('5551999990000');
});

test('"Este mês não" recusa o envio e passa o MESMO convite para a próxima congregação do rodízio', function () {
    Queue::fake();

    [$user, $team, $partner] = inviteButtonTeam();

    $next = Congregation::factory()->optedIn()->create([
        'owner_user_id' => $user->id,
        'name' => 'Cong. Norte',
        'contact_phone' => '51966665555',
    ]);

    $send = inviteButtonSend($team, $partner);
    $message = inviteButtonReply('Este mês não');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.BYE'));
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    $send->refresh();
    expect($send->status)->toBe(ExchangeInviteSendStatus::Declined)
        ->and($send->invite->sends()->count())->toBe(2)
        ->and(ExchangeInvite::query()->count())->toBe(1);

    $nextSend = $send->invite->sends()->where('congregation_id', $next->id)->sole();
    expect($nextSend->status)->toBe(ExchangeInviteSendStatus::Pending)
        ->and($nextSend->portal_token)->not->toBeNull();

    Queue::assertPushed(SendExchangeInvite::class, fn (SendExchangeInvite $job) => $job->send->is($nextSend));
});

test('resposta interactive list_reply do sandbox também aceita o convite', function () {
    [, $team, $partner] = inviteButtonTeam();

    $send = inviteButtonSend($team, $partner);

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.REPLY',
        'type' => 'interactive',
        'text' => null,
        'context_id' => 'wamid.OPENER',
        'payload' => [
            'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'opt_0', 'title' => 'Sim, vamos combinar']],
        ],
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.SESSION'));
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    expect($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Accepted)
        ->and($send->accepted_at)->not->toBeNull();
});

test('texto livre com convite pendente cai na mesa sem mudar o estado do envio', function () {
    [, $team, $partner] = inviteButtonTeam();

    $send = inviteButtonSend($team, $partner);

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.FREETEXT',
        'type' => 'text',
        'text' => 'Podemos conversar semana que vem?',
        'context_id' => 'wamid.OPENER',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->andReturn(SendResult::sent('cloud', 'wamid.ACK'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    expect($send->refresh()->status)->toBe(ExchangeInviteSendStatus::Answered)
        ->and($send->accepted_at)->toBeNull();
});
