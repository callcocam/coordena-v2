<?php

use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\Coordinator;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\TalkAssignmentNotification;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\InteractiveMessage;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: Team, 1: Coordinator}
 */
function conversationTeam(): array
{
    $team = User::factory()->create()->currentTeam;
    $coordinator = Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    return [$team, $coordinator];
}

function conversationInbound(string $text): WhatsAppInboundMessage
{
    return WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999990000',
        'wamid' => 'wamid.IN.'.fake()->unique()->lexify('??????'),
        'type' => 'text',
        'text' => $text,
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);
}

/**
 * Fake the Cloud API and collect every body the bot sends out.
 *
 * @return array<int, string> referência viva; cresce a cada envio do bot
 */
function &conversationOutbox(): array
{
    $outbox = [];

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendInteractive')
        ->andReturnUsing(function (string $to, InteractiveMessage $message) use (&$outbox) {
            $outbox[] = $message->body;

            return SendResult::sent('cloud', 'wamid.OUT.'.count($outbox));
        });
    $client->shouldReceive('sendSessionText')
        ->andReturnUsing(function (string $to, string $body) use (&$outbox) {
            $outbox[] = $body;

            return SendResult::sent('cloud', 'wamid.OUT.'.count($outbox));
        });
    WhatsApp::shouldReceive('for')->andReturn($client);

    return $outbox;
}

function conversationSay(string $text): void
{
    app(InboundDispatcher::class)->dispatch(conversationInbound($text));
}

function conversationWeekend(): Carbon
{
    $date = Carbon::today();

    return $date->dayOfWeek === Carbon::SATURDAY ? $date : $date->next(Carbon::SATURDAY);
}

function conversationPendingTalk(Team $team): TalkAssignment
{
    return TalkAssignment::factory()->for($team)->create([
        'date' => conversationWeekend()->toDateString(),
        'status' => TalkAssignmentStatus::Scheduled,
        'speaker_id' => Speaker::factory()->create(['phone' => '51988887777'])->id,
        'outline_id' => PublicTalkOutline::factory()->create()->id,
    ]);
}

test('"oi" de um coordenador abre a conversa no menu', function () {
    [$team, $coordinator] = conversationTeam();
    $outbox = &conversationOutbox();

    conversationSay('oi');

    $conversation = WhatsappConversation::query()->sole();

    expect($conversation->team_id)->toBe($team->id)
        ->and($conversation->coordinator_id)->toBe($coordinator->id)
        ->and($conversation->state)->toBe('menu')
        ->and($conversation->expires_at->isFuture())->toBeTrue()
        ->and($outbox)->toHaveCount(1)
        ->and($outbox[0])->toContain($coordinator->name);
});

test('do menu dá para navegar até a programação do fim de semana', function () {
    [$team] = conversationTeam();
    $assignment = conversationPendingTalk($team);
    $outbox = &conversationOutbox();

    conversationSay('oi');
    conversationSay(__('app.public_talks.conversation.menu.options.week_view'));

    $conversation = WhatsappConversation::query()->sole();

    expect($conversation->state)->toBe('week_view')
        ->and($conversation->contextValue('pending_ids'))->toBe([$assignment->id])
        ->and(end($outbox))->toContain($assignment->speaker->name)
        ->and(end($outbox))->toContain(conversationWeekend()->format('d/m'));
});

test('confirmar o disparo despacha exatamente os jobs pendentes', function () {
    Queue::fake();

    [$team] = conversationTeam();
    $assignment = conversationPendingTalk($team);
    $outbox = &conversationOutbox();

    conversationSay('oi');
    conversationSay(__('app.public_talks.conversation.menu.options.week_view'));
    conversationSay('Notificar orador');
    conversationSay(__('app.public_talks.conversation.options.yes'));

    Queue::assertPushed(SendSpeakerAssignmentNotification::class, 1);

    $notification = TalkAssignmentNotification::query()->sole();
    $conversation = WhatsappConversation::query()->sole();

    expect($notification->talk_assignment_id)->toBe($assignment->id)
        ->and($conversation->state)->toBe('menu')
        ->and($conversation->contextValue('pending_ids'))->toBe([]);
});

test('recusar o disparo volta ao menu sem despachar nada', function () {
    Queue::fake();

    [$team] = conversationTeam();
    conversationPendingTalk($team);
    conversationOutbox();

    conversationSay('oi');
    conversationSay(__('app.public_talks.conversation.menu.options.week_view'));
    conversationSay('Notificar orador');
    conversationSay(__('app.public_talks.conversation.options.no'));

    Queue::assertNothingPushed();

    expect(WhatsappConversation::query()->sole()->state)->toBe('menu');
});

test('entrada que não casa com opção nenhuma re-apresenta o estado com pedido de desculpa', function () {
    conversationTeam();
    $outbox = &conversationOutbox();

    conversationSay('oi');
    conversationSay('quero pizza');

    expect(WhatsappConversation::query()->sole()->state)->toBe('menu')
        ->and(end($outbox))->toContain(__('app.public_talks.conversation.unknown_reply'));
});

test('conversa expirada reinicia no menu, ignorando o estado antigo', function () {
    [$team, $coordinator] = conversationTeam();
    $outbox = &conversationOutbox();

    WhatsappConversation::query()->create([
        'team_id' => $team->id,
        'coordinator_id' => $coordinator->id,
        'phone' => '5551999990000',
        'state' => 'week_view',
        'context' => ['week_offset' => 3],
        'last_message_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    conversationSay('1');

    $conversation = WhatsappConversation::query()->sole();

    expect($conversation->state)->toBe('menu')
        ->and($conversation->contextValue('week_offset'))->toBeNull()
        ->and(end($outbox))->toContain($coordinator->name);
});

test('remetente desconhecido não abre conversa e cai nos slots seguintes', function () {
    conversationTeam();
    WhatsApp::shouldReceive('for')->never();

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551900000000',
        'wamid' => 'wamid.IN.STRANGER',
        'type' => 'text',
        'text' => 'oi',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    app(InboundDispatcher::class)->dispatch($message);

    expect(WhatsappConversation::query()->count())->toBe(0)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED);
});

test('botão de notificação de orador tem precedência sobre a conversa do coordenador', function () {
    [$team] = conversationTeam();

    // O coordenador também é orador: mesmo telefone nos dois cadastros.
    $speaker = Speaker::factory()->create(['phone' => '51999990000']);
    $assignment = TalkAssignment::factory()->for($team)->create([
        'speaker_id' => $speaker->id,
        'status' => TalkAssignmentStatus::Notified,
    ]);
    $notification = TalkAssignmentNotification::factory()->sent()->create([
        'talk_assignment_id' => $assignment->id,
        'speaker_id' => $speaker->id,
        'wamid' => 'wamid.OUTBOUND',
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->andReturn(SendResult::sent('cloud', 'wamid.ACK'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999990000',
        'wamid' => 'wamid.REPLY',
        'type' => 'button',
        'text' => 'Tudo certo',
        'context_id' => 'wamid.OUTBOUND',
        'payload' => ['button' => ['text' => 'Tudo certo', 'payload' => 'Tudo certo']],
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    app(InboundDispatcher::class)->dispatch($message);

    expect($notification->refresh()->status)->toBe(SpeakerNotificationStatus::Confirmed)
        ->and(WhatsappConversation::query()->count())->toBe(0);
});
