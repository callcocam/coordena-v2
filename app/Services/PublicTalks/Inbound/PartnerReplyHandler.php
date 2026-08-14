<?php

namespace App\Services\PublicTalks\Inbound;

use App\Enums\ExchangeInviteSendStatus;
use App\Models\ExchangeInviteSend;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

/**
 * Handler 3 — resposta de congregação parceira a um convite de troca vivo.
 *
 * Casa quando o telefone do remetente é o contato de uma congregação com um
 * `exchange_invite_send` recente em `sent|answered`. O texto NÃO é
 * interpretado (regra inviolável nº 4): vira `exchange_message` inbound
 * ÍNTEGRA no topo da mesa de trabalho, o send passa a `answered` e o
 * responsável recebe o link direto da mesa.
 */
class PartnerReplyHandler implements InboundHandler
{
    /**
     * Envio "vivo" = saiu há no máximo isto. Cobre o ciclo mensal do convite
     * com folga; respostas mais velhas caem na rede de segurança.
     */
    protected const LIVE_SEND_DAYS = 60;

    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        return $this->sendFor($message) !== null;
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
            'body' => $message->text ?? '['.$message->type.']',
            'wamid' => $message->wamid,
        ]);

        if ($send->status === ExchangeInviteSendStatus::Sent) {
            $send->update([
                'status' => ExchangeInviteSendStatus::Answered,
                'answered_at' => now(),
            ]);
        }

        $team = $send->invite->team;
        $link = route('public-talks.exchange.sends.show', ['current_team' => $team, 'send' => $send]);
        $month = $send->invite->month->translatedFormat('F \d\e Y');

        $this->alert->send(
            $team,
            "a Cong. {$send->congregation->name} respondeu o convite de troca de {$month} — abrir mesa de trabalho: {$link}",
        );

        $responsiblePhone = Phone::normalize($this->responsible->for($team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * The most recent live send whose partner congregation contact is the
     * sender, when any.
     */
    protected function sendFor(WhatsAppInboundMessage $message): ?ExchangeInviteSend
    {
        $phone = Phone::normalize($message->wa_id);

        if ($phone === null) {
            return null;
        }

        return ExchangeInviteSend::query()
            ->whereIn('status', [ExchangeInviteSendStatus::Sent, ExchangeInviteSendStatus::Accepted, ExchangeInviteSendStatus::Answered])
            ->where('sent_at', '>=', now()->subDays(self::LIVE_SEND_DAYS))
            ->whereHas('congregation', fn ($query) => $query->where('contact_phone', $phone))
            ->with(['invite.team', 'congregation'])
            ->orderByDesc('sent_at')
            ->first();
    }
}
