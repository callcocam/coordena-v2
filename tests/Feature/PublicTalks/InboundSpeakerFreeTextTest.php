<?php

use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Models\Coordinator;
use App\Models\Speaker;
use App\Models\TalkAssignment;
use App\Models\TalkAssignmentNotification;
use App\Models\User;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

test('texto livre de orador com notificação viva é encaminhado íntegro, sem mudar status', function () {
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create([
        'name' => 'Carlos',
        'phone' => '51999990000',
    ]);

    $speaker = Speaker::factory()->create(['name' => 'João', 'phone' => '51988887777']);
    $assignment = TalkAssignment::factory()->for($team)->create([
        'speaker_id' => $speaker->id,
        'status' => TalkAssignmentStatus::Notified,
        'date' => now()->addDays(10)->toDateString(),
    ]);
    $notification = TalkAssignmentNotification::factory()->sent()->create([
        'talk_assignment_id' => $assignment->id,
        'speaker_id' => $speaker->id,
        'wamid' => 'wamid.OUTBOUND',
    ]);

    // Janela de 24h do coordenador aberta → o encaminhamento sai por sessão,
    // preservando as quebras de linha do texto original.
    WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999990000',
        'wamid' => 'wamid.COORD',
        'type' => 'text',
        'text' => 'Oi',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $body = "Posso, mas só depois das 10h.\nMe confirma o horário?";
    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551988887777',
        'wamid' => 'wamid.FREETEXT',
        'type' => 'text',
        'text' => $body,
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $date = $assignment->date->translatedFormat('d/m');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')
        ->once()
        ->withArgs(function (string $to, string $text) use ($body, $date) {
            expect($to)->toBe('5551999990000')
                ->and($text)->toContain('João')
                ->and($text)->toContain($date)
                ->and($text)->toContain($body);

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.FWD'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    expect($notification->refresh()->status)->toBe(SpeakerNotificationStatus::Sent)
        ->and($assignment->refresh()->status)->toBe(TalkAssignmentStatus::Notified)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED)
        ->and($message->forwarded_to)->toBe('5551999990000');
});

test('orador sem notificação viva não casa no handler de texto livre', function () {
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    $speaker = Speaker::factory()->create(['phone' => '51988887777']);
    TalkAssignmentNotification::factory()->create([
        'speaker_id' => $speaker->id,
        'status' => SpeakerNotificationStatus::Sent,
        'sent_at' => now()->subDays(90),
    ]);

    WhatsApp::shouldReceive('for')->never();

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551988887777',
        'wamid' => 'wamid.STALE',
        'type' => 'text',
        'text' => 'Oi, alguém aí?',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    app(InboundDispatcher::class)->dispatch($message);

    expect($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_UNHANDLED);
});
