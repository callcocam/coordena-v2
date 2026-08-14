<?php

use App\Enums\CongregationIntroStatus;
use App\Enums\ExchangeOpt;
use App\Jobs\SendCongregationIntro;
use App\Models\Congregation;
use App\Models\CongregationIntro;
use App\Models\Coordinator;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Models\User;
use App\Services\PublicTalks\Inbound\InboundDispatcher;
use Callcocam\WhatsAppCloud\CloudApiClient;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\SendResult;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Queue;

function introUser(): User
{
    $user = User::factory()->create();
    Coordinator::factory()->responsible()->for($user->currentTeam)->create([
        'name' => 'Carlos',
        'phone' => '51999990000',
    ]);

    return $user;
}

function introCongregation(Team $team, array $attributes = []): Congregation
{
    return Congregation::factory()->create(array_merge([
        'owner_user_id' => $team->owner()?->id,
        'contact_phone' => '51977776666',
    ], $attributes));
}

function introInbound(Team $team, array $attributes = []): WhatsAppInboundMessage
{
    $connection = TeamWhatsappConnection::query()->firstWhere('team_id', $team->id)
        ?? TeamWhatsappConnection::factory()->create(['team_id' => $team->id]);

    return WhatsAppInboundMessage::query()->create(array_merge([
        'wa_id' => '5551977776666',
        'wamid' => 'wamid.IN.'.fake()->unique()->lexify('??????'),
        'phone_number_id' => $connection->phone_number_id,
        'type' => 'text',
        'text' => 'Olá!',
        'status' => WhatsAppInboundMessage::STATUS_RECEIVED,
    ], $attributes));
}

function introButtonReply(Team $team, string $label, ?string $contextId): WhatsAppInboundMessage
{
    return introInbound($team, [
        'type' => 'button',
        'text' => $label,
        'context_id' => $contextId,
        'payload' => ['button' => ['text' => $label]],
    ]);
}

// ─── Envio (controller + job) ───────────────────────────────────────────────

test('enviar apresentação cria a intro pendente e enfileira o job', function () {
    Queue::fake();
    $user = introUser();
    $congregation = introCongregation($user->currentTeam);

    $response = $this->actingAs($user)->post(route('acervo.congregations.intro.store', [
        'current_team' => $user->currentTeam->slug,
        'congregation' => $congregation->id,
    ]));

    $response->assertRedirect();

    $intro = CongregationIntro::query()->sole();

    expect($intro->status)->toBe(CongregationIntroStatus::Pending)
        ->and($intro->congregation_id)->toBe($congregation->id)
        ->and($intro->sent_by_id)->toBe($user->id)
        ->and($intro->portal_token)->not->toBeNull();

    Queue::assertPushed(SendCongregationIntro::class, 1);
});

test('sem telefone válido nada é criado nem enfileirado', function () {
    Queue::fake();
    $user = introUser();
    $congregation = introCongregation($user->currentTeam, ['contact_phone' => null]);

    $this->actingAs($user)->post(route('acervo.congregations.intro.store', [
        'current_team' => $user->currentTeam->slug,
        'congregation' => $congregation->id,
    ]))->assertRedirect();

    expect(CongregationIntro::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('não reenvia enquanto uma intro do par aguarda resposta', function () {
    Queue::fake();
    $user = introUser();
    $congregation = introCongregation($user->currentTeam);

    CongregationIntro::factory()->sent()->create([
        'team_id' => $user->currentTeam->id,
        'congregation_id' => $congregation->id,
    ]);

    $this->actingAs($user)->post(route('acervo.congregations.intro.store', [
        'current_team' => $user->currentTeam->slug,
        'congregation' => $congregation->id,
    ]))->assertRedirect();

    expect(CongregationIntro::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('o job envia o template, grava o wamid e move pending → sent', function () {
    $user = introUser();
    $congregation = introCongregation($user->currentTeam);
    $intro = CongregationIntro::factory()->create([
        'team_id' => $user->currentTeam->id,
        'congregation_id' => $congregation->id,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->once()->andReturn(SendResult::sent('cloud', 'wamid.INTRO'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    app()->call([new SendCongregationIntro($intro), 'handle']);

    $intro->refresh();

    expect($intro->status)->toBe(CongregationIntroStatus::Sent)
        ->and($intro->wamid)->toBe('wamid.INTRO')
        ->and($intro->sent_at)->not->toBeNull()
        ->and($intro->messages()->where('direction', 'outbound')->count())->toBe(1);
});

test('o job é idempotente: intro fora de pending não envia nada', function () {
    $user = introUser();
    $intro = CongregationIntro::factory()->sent()->create([
        'team_id' => $user->currentTeam->id,
        'congregation_id' => introCongregation($user->currentTeam)->id,
    ]);

    WhatsApp::shouldReceive('for')->never();

    app()->call([new SendCongregationIntro($intro), 'handle']);

    expect($intro->refresh()->status)->toBe(CongregationIntroStatus::Sent);
});

test('quando o job desiste a intro fica marcada como failed', function () {
    $user = introUser();
    $intro = CongregationIntro::factory()->create([
        'team_id' => $user->currentTeam->id,
        'congregation_id' => introCongregation($user->currentTeam)->id,
    ]);

    (new SendCongregationIntro($intro))->failed(null);

    expect($intro->refresh()->status)->toBe(CongregationIntroStatus::Failed);
});

// ─── Cenário A/B: botões do opt-in ──────────────────────────────────────────

test('cenário A: "Sim, aceito" correlacionado pelo wamid aceita e faz opt-in', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::Unknown]);
    $intro = CongregationIntro::factory()->sent()->create([
        'team_id' => $team->id,
        'congregation_id' => $congregation->id,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.REPLY'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introButtonReply($team, 'Sim, aceito', $intro->wamid);

    app(InboundDispatcher::class)->dispatch($message);

    $intro->refresh();

    expect($intro->status)->toBe(CongregationIntroStatus::Accepted)
        ->and($intro->responded_at)->not->toBeNull()
        ->and($congregation->refresh()->exchange_opt)->toBe(ExchangeOpt::OptedIn)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED);
});

test('cenário B: "Agora não" recusa e faz opt-out', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::Unknown]);
    $intro = CongregationIntro::factory()->sent()->create([
        'team_id' => $team->id,
        'congregation_id' => $congregation->id,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.REPLY'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introButtonReply($team, 'Agora não', $intro->wamid);

    app(InboundDispatcher::class)->dispatch($message);

    $intro->refresh();

    expect($intro->status)->toBe(CongregationIntroStatus::Declined)
        ->and($intro->declined_at)->not->toBeNull()
        ->and($congregation->refresh()->exchange_opt)->toBe(ExchangeOpt::OptedOut);
});

test('botão de opt-in sem context.id não altera intro nenhuma', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::Unknown]);
    $intro = CongregationIntro::factory()->sent()->create([
        'team_id' => $team->id,
        'congregation_id' => $congregation->id,
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introButtonReply($team, 'Sim, aceito', null);

    app(InboundDispatcher::class)->dispatch($message);

    expect($intro->refresh()->status)->toBe(CongregationIntroStatus::Sent)
        ->and($congregation->refresh()->exchange_opt)->toBe(ExchangeOpt::Unknown);
});

// ─── Cenário C/D: reativação de opted-out ───────────────────────────────────

test('cenário C: opted-out que escreve recebe o prompt de reativação', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::OptedOut]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendInteractive')->once()->andReturn(SendResult::sent('cloud', 'wamid.PROMPT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introInbound($team, ['text' => 'Podemos voltar a receber oradores?']);

    app(InboundDispatcher::class)->dispatch($message);

    $intro = CongregationIntro::query()->sole();

    expect($intro->reactivation_wamid)->toBe('wamid.PROMPT')
        ->and($intro->reactivation_prompted_at)->not->toBeNull();
});

test('cenário C: "Voltar a fazer trocas" reativa e faz opt-in', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::OptedOut]);
    $intro = CongregationIntro::factory()->declined()->create([
        'team_id' => $team->id,
        'congregation_id' => $congregation->id,
        'reactivation_wamid' => 'wamid.PROMPT',
        'reactivation_prompted_at' => now(),
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.REPLY'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introInbound($team, [
        'type' => 'interactive',
        'text' => null,
        'context_id' => 'wamid.PROMPT',
        'payload' => ['interactive' => ['button_reply' => ['title' => 'Voltar a fazer trocas']]],
    ]);

    app(InboundDispatcher::class)->dispatch($message);

    expect($intro->refresh()->status)->toBe(CongregationIntroStatus::Accepted)
        ->and($intro->reactivated_at)->not->toBeNull()
        ->and($congregation->refresh()->exchange_opt)->toBe(ExchangeOpt::OptedIn);
});

test('cenário D: depois do prompt a mensagem vai para o coordenador', function () {
    $user = introUser();
    $team = $user->currentTeam;
    $congregation = introCongregation($team, ['exchange_opt' => ExchangeOpt::OptedOut]);
    CongregationIntro::factory()->declined()->create([
        'team_id' => $team->id,
        'congregation_id' => $congregation->id,
        'reactivation_wamid' => 'wamid.PROMPT',
        'reactivation_prompted_at' => now(),
    ]);

    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('sendSessionText')->once()->andReturn(SendResult::sent('cloud', 'wamid.REPLY'));
    $client->shouldReceive('sendTemplate')->andReturn(SendResult::sent('cloud', 'wamid.ALERT'));
    WhatsApp::shouldReceive('for')->andReturn($client);

    $message = introInbound($team, ['text' => 'Quero falar sobre um discurso especial']);

    app(InboundDispatcher::class)->dispatch($message);

    expect($congregation->refresh()->exchange_opt)->toBe(ExchangeOpt::OptedOut)
        ->and($message->refresh()->status)->toBe(WhatsAppInboundMessage::STATUS_FORWARDED);
});

// ─── Portal público ─────────────────────────────────────────────────────────

test('o portal público abre pelo token da intro', function () {
    $user = introUser();
    $intro = CongregationIntro::factory()->sent()->create([
        'team_id' => $user->currentTeam->id,
        'congregation_id' => introCongregation($user->currentTeam)->id,
    ]);

    $this->withoutVite()->get(route('intro.portal', $intro->portal_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('publicTalks/IntroPortal'));
});

test('token desconhecido no portal responde 404', function () {
    $this->get(route('intro.portal', 'nope'))->assertNotFound();
});
