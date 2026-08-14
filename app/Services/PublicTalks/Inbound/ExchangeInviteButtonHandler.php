<?php

namespace App\Services\PublicTalks\Inbound;

use App\Enums\ExchangeInviteKind;
use App\Enums\ExchangeInviteSendStatus;
use App\Jobs\SendExchangeInvite;
use App\Models\ExchangeInviteSend;
use App\Models\ExchangeMessage;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ExchangeRoundRobin;
use App\Services\PublicTalks\InviteComposer;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handler — botão do convite de troca/ajuda, correlacionado por wamid.
 *
 * O quick reply chega com `context.id` apontando para o `wamid` que o
 * {@see SendExchangeInvite} gravou na `exchange_message` outbound do envio.
 * É essa correlação — nunca o rótulo sozinho — que identifica o fluxo:
 *
 * - Aceite ("Sim, vamos combinar" / "Podemos ajudar") → send `accepted` e SÓ
 *   AGORA a mensagem de sessão leva as semanas em aberto, nossos oradores
 *   (variante troca) e o link do portal ({@see InviteComposer::acceptedSession}).
 * - "Este mês não" → send `declined`, agradecimento na sessão e o MESMO
 *   convite segue para a próxima congregação do rodízio
 *   ({@see ExchangeRoundRobin::nextFor}) — nunca um convite novo.
 *
 * Vem antes do PartnerReplyHandler na cadeia porque a correlação explícita
 * (context_id) é mais forte que o casamento por telefone do convite vivo, que
 * capturaria o clique como texto de mesa. Texto livre segue caindo na mesa.
 */
class ExchangeInviteButtonHandler implements InboundHandler
{
    public function __construct(
        protected InviteComposer $composer,
        protected ExchangeRoundRobin $roundRobin,
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        $send = $this->sendFor($message);

        if ($send === null) {
            return false;
        }

        return in_array($this->buttonText($message), [
            $send->kind->acceptLabel(),
            $send->kind->declineLabel(),
        ], true);
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $send = $this->sendFor($message);

        if ($send === null) {
            $message->markUnhandled();

            return;
        }

        $send->messages()->create([
            'direction' => 'inbound',
            'channel' => 'whatsapp',
            'body' => (string) $this->buttonText($message),
            'wamid' => $message->wamid,
        ]);

        $this->buttonText($message) === $send->kind->acceptLabel()
            ? $this->accept($send, $message)
            : $this->decline($send, $message);

        $responsiblePhone = Phone::normalize($this->responsible->for($send->invite->team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * Accept: flip the send to `accepted` and only then let the rich session
     * message travel (open weeks + our speakers + portal link — the exposure
     * gate of melhoria 3).
     */
    protected function accept(ExchangeInviteSend $send, WhatsAppInboundMessage $message): void
    {
        $send->update([
            'status' => ExchangeInviteSendStatus::Accepted,
            'answered_at' => $send->answered_at ?? now(),
            'accepted_at' => now(),
        ]);

        $this->replyInSession($send, $message, $this->composer->acceptedSession($send));

        $this->alert->send($send->invite->team, __($send->kind === ExchangeInviteKind::Help
            ? 'app.public_talks.exchange.alerts.accepted_help'
            : 'app.public_talks.exchange.alerts.accepted', [
                'congregation' => $send->congregation->name,
                'month' => $send->invite->month->translatedFormat('F \d\e Y'),
            ]));
    }

    /**
     * Decline: thank in session, close this send and pass the SAME invite to
     * the next congregation of the round robin (a fresh pending send, never a
     * new invite).
     */
    protected function decline(ExchangeInviteSend $send, WhatsAppInboundMessage $message): void
    {
        $send->update([
            'status' => ExchangeInviteSendStatus::Declined,
            'answered_at' => $send->answered_at ?? now(),
        ]);

        $this->replyInSession($send, $message, __('app.public_talks.exchange.wa.declined_reply'));

        $invite = $send->invite;
        $next = $this->roundRobin->nextFor($invite);
        $month = $invite->month->translatedFormat('F \d\e Y');

        if ($next === null) {
            $this->alert->send($invite->team, __('app.public_talks.exchange.alerts.declined_end', [
                'congregation' => $send->congregation->name,
                'month' => $month,
            ]));

            return;
        }

        $nextSend = $invite->sends()->create([
            'congregation_id' => $next->id,
            'channel' => 'whatsapp',
            'portal_token' => Str::random(48),
            'status' => ExchangeInviteSendStatus::Pending,
        ]);

        SendExchangeInvite::dispatch($nextSend);

        $this->alert->send($invite->team, __('app.public_talks.exchange.alerts.declined_next', [
            'congregation' => $send->congregation->name,
            'month' => $month,
            'next' => $next->name,
        ]));
    }

    /**
     * Session reply — the button tap just opened the 24h window. Best effort:
     * a failure here must not undo the state change.
     */
    protected function replyInSession(ExchangeInviteSend $send, WhatsAppInboundMessage $message, string $text): void
    {
        try {
            WhatsApp::for($send->invite->team)->sendSessionText($message->wa_id, $text);

            $send->messages()->create([
                'direction' => 'outbound',
                'channel' => 'whatsapp',
                'body' => $text,
            ]);
        } catch (CloudApiException $exception) {
            Log::warning('Exchange invite button reply not delivered.', [
                'wamid' => $message->wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The live send whose outbound wamid the reply quotes, when any.
     */
    protected function sendFor(WhatsAppInboundMessage $message): ?ExchangeInviteSend
    {
        if ($message->context_id === null) {
            return null;
        }

        $outbound = ExchangeMessage::query()
            ->where('direction', 'outbound')
            ->where('wamid', $message->context_id)
            ->first();

        if ($outbound === null) {
            return null;
        }

        $send = $outbound->inviteSend()->with(['invite.team', 'congregation'])->first();

        if ($send === null || ! in_array($send->status, [
            ExchangeInviteSendStatus::Sent,
            ExchangeInviteSendStatus::Answered,
        ], true)) {
            return null;
        }

        return $send;
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
