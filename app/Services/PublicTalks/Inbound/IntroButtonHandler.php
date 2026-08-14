<?php

namespace App\Services\PublicTalks\Inbound;

use App\Enums\CongregationIntroStatus;
use App\Enums\ExchangeOpt;
use App\Jobs\SendCongregationIntro;
use App\Models\CongregationIntro;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;

/**
 * Handler 2 — botão da apresentação (`coordena_intro`), correlacionado por wamid.
 *
 * O quick reply chega com `context.id` apontando para o `wamid` que o
 * {@see SendCongregationIntro} gravou na intro. É essa correlação —
 * nunca o rótulo sozinho — que identifica o fluxo:
 *
 * - "Sim, aceito" → intro `accepted`, congregação `opted_in`, boas-vindas na
 *   sessão e aviso ao coordenador. A congregação entra no rodízio de trocas.
 * - "Agora não"   → intro `declined`, congregação `opted_out`, resposta
 *   cordial (pode nos chamar se mudar de ideia) com o telefone do coordenador
 *   para outros assuntos.
 *
 * Vem antes do PartnerReplyHandler na cadeia porque a resposta ao botão da
 * intro carrega correlação explícita (context_id) — mais forte que o casamento
 * por telefone de um convite vivo, que capturaria a mensagem por engano.
 */
class IntroButtonHandler implements InboundHandler
{
    /**
     * Rótulos dos quick replies de `coordena_intro`
     * (ver database/whatsapp-templates/coordena_intro.php).
     */
    protected const LABEL_ACCEPT = 'Sim, aceito';

    protected const LABEL_DECLINE = 'Agora não';

    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        if (! in_array($this->buttonText($message), [self::LABEL_ACCEPT, self::LABEL_DECLINE], true)) {
            return false;
        }

        return $this->introFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $intro = $this->introFor($message);

        if ($intro === null) {
            $message->markUnhandled();

            return;
        }

        $accepted = $this->buttonText($message) === self::LABEL_ACCEPT;

        $intro->messages()->create([
            'direction' => 'inbound',
            'channel' => 'whatsapp',
            'body' => (string) $this->buttonText($message),
            'wamid' => $message->wamid,
        ]);

        $intro->update([
            'status' => $accepted ? CongregationIntroStatus::Accepted : CongregationIntroStatus::Declined,
            'responded_at' => now(),
            'declined_at' => $accepted ? null : now(),
        ]);

        $intro->congregation->update([
            'exchange_opt' => $accepted ? ExchangeOpt::OptedIn : ExchangeOpt::OptedOut,
        ]);

        $this->replyToCongregation($intro, $message, $accepted);

        $this->alert->send($intro->team, __($accepted
            ? 'app.public_talks.intro.alerts.accepted'
            : 'app.public_talks.intro.alerts.declined', [
                'congregation' => $intro->congregation->name,
            ]));

        $responsiblePhone = Phone::normalize($this->responsible->for($intro->team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * Session reply to the congregation — the button tap just opened the 24h
     * window. Best effort: a failure here must not undo the state change.
     */
    protected function replyToCongregation(CongregationIntro $intro, WhatsAppInboundMessage $message, bool $accepted): void
    {
        $coordinator = $this->responsible->for($intro->team);

        $text = __($accepted
            ? 'app.public_talks.intro.wa.accepted_reply'
            : 'app.public_talks.intro.wa.declined_reply', [
                'coordinator' => $coordinator?->name ?? $intro->team->name,
                'phone' => $coordinator?->phone ?? '—',
            ]);

        try {
            WhatsApp::for($intro->team)->sendSessionText($message->wa_id, $text);

            $intro->messages()->create([
                'direction' => 'outbound',
                'channel' => 'whatsapp',
                'body' => $text,
            ]);
        } catch (CloudApiException $exception) {
            Log::warning('Intro button reply not delivered.', [
                'wamid' => $message->wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The intro whose outbound wamid the reply quotes, when any.
     */
    protected function introFor(WhatsAppInboundMessage $message): ?CongregationIntro
    {
        if ($message->context_id === null) {
            return null;
        }

        return CongregationIntro::query()
            ->where('wamid', $message->context_id)
            ->where('status', CongregationIntroStatus::Sent)
            ->with(['team', 'congregation'])
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
}
