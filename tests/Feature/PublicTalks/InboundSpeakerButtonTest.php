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

/**
 * @return array{0: TalkAssignment, 1: TalkAssignmentNotification, 2: WhatsAppInboundMessage}
 */
function speakerButtonScenario(string $label): array
{
    $team = User::factory()->create()->currentTeam;
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);

    $speaker = Speaker::factory()->create(['phone' => '51988887777']);
    $assignment = TalkAssignment::factory()->for($team)->create([
        'speaker_id' => $speaker->id,
        'status' => TalkAssignmentStatus::Notified,
    ]);
    $notification = TalkAssignmentNotification::factory()->sent()->create([
        'talk_assignment_id' => $assignment->id,
        'speaker_id' => $speaker->id,
        'wamid' => 'wamid.OUTBOUND',
    ]);

    $message = WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551988887777',
        'wamid' => 'wamid.REPLY',
        'type' => 'button',
        'text' => $label,
        'context_id' => 'wamid.OUTBOUND',
        'payload' => ['button' => ['text' => $label, 'payload' => $label]],
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    return [$assignment, $notification, $message];
}

test('"Tudo certo" confirma notificação e assignment, agradece o orador e avisa o coordenador', function () {
    [$assignment, $notification, $message] = speakerButtonScenario('Tudo certo');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')
        ->once()
        ->withArgs(function (string $to, string $text) {
            expect($to)->toBe('5551988887777')
                ->and($text)->toContain('Obrigado por confirmar');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ACK'));
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, $template) {
            expect($to)->toBe('5551999990000')
                ->and($template->params['summary'])->toContain('confirmou o discurso');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    $notification->refresh();

    expect($notification->status)->toBe(SpeakerNotificationStatus::Confirmed)
        ->and($notification->responded_at)->not->toBeNull()
        ->and($notification->response_payload['button'])->toBe('Tudo certo')
        ->and($notification->response_payload['wamid'])->toBe('wamid.REPLY')
        ->and($assignment->refresh()->status)->toBe(TalkAssignmentStatus::Confirmed)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED)
        ->and($message->forwarded_to)->toBe('5551999990000');
});

test('"Preciso remarcar" marca a notificação e move o assignment para reprogramar', function () {
    [$assignment, $notification, $message] = speakerButtonScenario('Preciso remarcar');

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.ACK'));
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, $template) {
            expect($template->params['summary'])->toContain('pediu para remarcar');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(InboundDispatcher::class)->dispatch($message);

    expect($notification->refresh()->status)->toBe(SpeakerNotificationStatus::RescheduleRequested)
        ->and($assignment->refresh()->status)->toBe(TalkAssignmentStatus::NeedsReschedule)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED);
});
