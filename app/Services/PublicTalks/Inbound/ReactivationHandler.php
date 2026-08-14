<?php

namespace App\Services\PublicTalks\Inbound;

use App\Enums\CongregationIntroStatus;
use App\Enums\ExchangeOpt;
use App\Models\Congregation;
use App\Models\CongregationIntro;
use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Messages\InteractiveMessage;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;

/**
 * Handler 6 — mensagem espontânea de congregação `opted_out`.
 *
 * Uma congregação que recusou as trocas mandou mensagem (fora de qualquer
 * convite vivo — os handlers anteriores já teriam capturado). O texto NUNCA é
 * interpretado (regra inviolável nº 4):
 *
 * - Primeira mensagem → registra e responde propondo reativação com os botões
 *   "Voltar a fazer trocas" / "Falar com o coordenador" (o wamid do prompt
 *   fica em `reactivation_wamid` para correlacionar a resposta).
 * - Botão "Voltar a fazer trocas" (correlacionado por wamid) → `opted_in` +
 *   intro `accepted` com `reactivated_at` + confirmação.
 * - Botão "Falar com o coordenador" OU texto livre após o prompt → encaminha
 *   a mensagem ÍNTEGRA ao responsável e responde com o telefone dele.
 *
 * Fica logo antes do SafetyNetHandler: só captura o que nenhum fluxo vivo
 * reconheceu, e apenas de remetentes conhecidos como `opted_out`.
 */
class ReactivationHandler implements InboundHandler
{
    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        return $this->teamFor($message) !== null && $this->congregationFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $team = $this->teamFor($message);
        $congregation = $this->congregationFor($message);

        if ($team === null || $congregation === null) {
            $message->markUnhandled();

            return;
        }

        $intro = $this->introFor($team, $congregation);

        $intro->messages()->create([
            'direction' => 'inbound',
            'channel' => 'whatsapp',
            'body' => $message->text ?? $this->buttonText($message) ?? '['.$message->type.']',
            'wamid' => $message->wamid,
        ]);

        $button = $this->promptButtonFor($intro, $message);

        if ($button === 'accept') {
            $this->reactivate($intro, $message);

            return;
        }

        if ($button === 'talk' || $intro->reactivation_prompted_at !== null) {
            $this->forwardToCoordinator($intro, $message);

            return;
        }

        $this->sendReactivationPrompt($intro, $message);
    }

    /**
     * "Voltar a fazer trocas": opt-in de volta, com auditoria na intro.
     */
    protected function reactivate(CongregationIntro $intro, WhatsAppInboundMessage $message): void
    {
        $intro->update([
            'status' => CongregationIntroStatus::Accepted,
            'responded_at' => now(),
            'reactivated_at' => now(),
        ]);

        $intro->congregation->update(['exchange_opt' => ExchangeOpt::OptedIn]);

        $this->reply($intro, $message, __('app.public_talks.intro.wa.reactivated_reply'));

        $this->alert->send($intro->team, __('app.public_talks.intro.alerts.reactivated', [
            'congregation' => $intro->congregation->name,
        ]));

        $this->forward($intro, $message);
    }

    /**
     * "Falar com o coordenador" ou texto livre depois do prompt: encaminha a
     * mensagem íntegra e deixa o telefone do coordenador com a congregação.
     */
    protected function forwardToCoordinator(CongregationIntro $intro, WhatsAppInboundMessage $message): void
    {
        $coordinator = $this->responsible->for($intro->team);

        $this->reply($intro, $message, __('app.public_talks.intro.wa.forward_reply', [
            'coordinator' => $coordinator?->name ?? $intro->team->name,
            'phone' => $coordinator?->phone ?? '—',
        ]));

        $this->alert->send($intro->team, __('app.public_talks.intro.alerts.talk_requested', [
            'congregation' => $intro->congregation->name,
        ]));

        $this->forward($intro, $message);
    }

    /**
     * First spontaneous message: propose reactivation with reply buttons and
     * remember the prompt wamid to correlate the answer.
     */
    protected function sendReactivationPrompt(CongregationIntro $intro, WhatsAppInboundMessage $message): void
    {
        $body = __('app.public_talks.intro.wa.reactivation_prompt');

        try {
            $result = WhatsApp::for($intro->team)->sendInteractive(
                $message->wa_id,
                new InteractiveMessage($body, [
                    __('app.public_talks.intro.wa.reactivation_buttons.accept'),
                    __('app.public_talks.intro.wa.reactivation_buttons.talk'),
                ]),
            );
        } catch (CloudApiException $exception) {
            Log::warning('Reactivation prompt not delivered.', [
                'wamid' => $message->wamid,
                'error' => $exception->getMessage(),
            ]);

            $this->forwardToCoordinator($intro, $message);

            return;
        }

        $intro->messages()->create([
            'direction' => 'outbound',
            'channel' => 'whatsapp',
            'body' => $body,
            'wamid' => $result->messageId,
        ]);

        $intro->update([
            'reactivation_wamid' => $result->messageId,
            'reactivation_prompted_at' => now(),
        ]);

        $message->markUnhandled();
    }

    /**
     * Which reactivation button was tapped, correlated to OUR prompt by
     * wamid (`context.id`) — never by label alone.
     *
     * @return 'accept'|'talk'|null
     */
    protected function promptButtonFor(CongregationIntro $intro, WhatsAppInboundMessage $message): ?string
    {
        if ($intro->reactivation_wamid === null || $message->context_id !== $intro->reactivation_wamid) {
            return null;
        }

        return match ($this->buttonText($message)) {
            __('app.public_talks.intro.wa.reactivation_buttons.accept') => 'accept',
            __('app.public_talks.intro.wa.reactivation_buttons.talk') => 'talk',
            default => null,
        };
    }

    /**
     * Session reply to the congregation, recorded in the intro history.
     * Best effort: a failure must not undo the state change.
     */
    protected function reply(CongregationIntro $intro, WhatsAppInboundMessage $message, string $text): void
    {
        try {
            WhatsApp::for($intro->team)->sendSessionText($message->wa_id, $text);

            $intro->messages()->create([
                'direction' => 'outbound',
                'channel' => 'whatsapp',
                'body' => $text,
            ]);
        } catch (CloudApiException $exception) {
            Log::warning('Reactivation reply not delivered.', [
                'wamid' => $message->wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Forward the untouched message to the responsible coordinator.
     */
    protected function forward(CongregationIntro $intro, WhatsAppInboundMessage $message): void
    {
        $responsiblePhone = Phone::normalize($this->responsible->for($intro->team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * The opted-out congregation whose contact is the sender, when any.
     */
    protected function congregationFor(WhatsAppInboundMessage $message): ?Congregation
    {
        $phone = Phone::normalize($message->wa_id);

        if ($phone === null) {
            return null;
        }

        return Congregation::query()
            ->where('exchange_opt', ExchangeOpt::OptedOut)
            ->where('contact_phone', $phone)
            ->first();
    }

    /**
     * The tapped button label: template quick replies come as `type: button`,
     * interactive replies as `interactive.button_reply`.
     */
    protected function buttonText(WhatsAppInboundMessage $message): ?string
    {
        $payload = $message->payload ?? [];

        $text = $payload['button']['text']
            ?? $payload['interactive']['button_reply']['title']
            ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * The team owning the Cloud number that received the message, when any.
     */
    protected function teamFor(WhatsAppInboundMessage $message): ?Team
    {
        if ($message->phone_number_id === null) {
            return null;
        }

        return TeamWhatsappConnection::query()
            ->where('phone_number_id', $message->phone_number_id)
            ->first()
            ?->team;
    }

    /**
     * The latest intro of the pair — the audit record every step lands on.
     * A congregation opted out by hand may not have one yet.
     */
    protected function introFor(Team $team, Congregation $congregation): CongregationIntro
    {
        $intro = CongregationIntro::query()
            ->forPair($team, $congregation)
            ->latest('created_at')
            ->first();

        return $intro ?? CongregationIntro::query()->create([
            'team_id' => $team->id,
            'congregation_id' => $congregation->id,
            'channel' => 'whatsapp',
            'status' => CongregationIntroStatus::Declined,
            'declined_at' => now(),
        ]);
    }
}
