<?php

use App\Models\Coordinator;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicTalks\CoordinatorAlert;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Messages\TemplateMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;

function alertTeam(): Team
{
    return User::factory()->create()->currentTeam;
}

test('alert goes out as template when the 24h window is closed', function () {
    $team = alertTeam();
    $coordinator = Coordinator::factory()->responsible()->for($team)->create([
        'name' => 'Carlos',
        'phone' => '51999998888',
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->withArgs(function (string $to, TemplateMessage $template) {
            expect($to)->toBe('5551999998888');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->with(Mockery::on(fn ($t) => $t->is($team)))->andReturn($client);

    app(CoordinatorAlert::class)->send($team, 'o orador João confirmou o discurso de domingo, 12/07.');
});

test('alert uses a session text when the coordinator wrote within 24h', function () {
    $team = alertTeam();
    Coordinator::factory()->responsible()->for($team)->create([
        'name' => 'Carlos',
        'phone' => '51999998888',
    ]);

    WhatsAppInboundMessage::query()->create([
        'wa_id' => '5551999998888',
        'wamid' => 'wamid.INBOUND',
        'type' => 'text',
        'text' => 'Oi',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')
        ->once()
        ->withArgs(function (string $to, string $text) {
            expect($to)->toBe('5551999998888')
                ->and($text)->toContain('*Carlos*')
                ->and($text)->toContain('convite de permuta aceito');

            return true;
        })
        ->andReturn(SendResult::sent('cloud', 'wamid.SESSION'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app(CoordinatorAlert::class)->send($team, 'convite de permuta aceito pela outra congregação.');
});

test('a failed recipient is logged and does not stop the others', function () {
    $team = alertTeam();
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '51999990000']);
    Coordinator::factory()->for($team)->create(['phone' => '51999991111']);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')
        ->once()
        ->andThrow(new CloudApiException('Template paused.'));
    $client->shouldReceive('sendTemplate')
        ->once()
        ->andReturn(SendResult::sent('cloud', 'wamid.SECOND'));
    WhatsApp::shouldReceive('for')->andReturn($client);
    Log::shouldReceive('warning')->once();

    app(CoordinatorAlert::class)->send($team, 'algo mudou na programação.');
});

test('coordinators without a valid phone are skipped', function () {
    $team = alertTeam();
    Coordinator::factory()->responsible()->for($team)->create(['phone' => '123']);

    WhatsApp::shouldReceive('for')->never();

    app(CoordinatorAlert::class)->send($team, 'nada a enviar.');
});
