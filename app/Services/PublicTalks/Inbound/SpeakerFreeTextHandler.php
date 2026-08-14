<?php

namespace App\Services\PublicTalks\Inbound;

use App\Models\TalkAssignmentNotification;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;

/**
 * Handler 4 — texto livre de orador conhecido com notificação viva.
 *
 * Casa quando o remetente é o telefone de um orador que recebeu uma
 * notificação de discurso nos últimos dias. O corpo vai ÍNTEGRO ao
 * responsável e ajudantes (regra inviolável nº 4 — sem IA, sem parsing):
 * "O orador X, discurso de DD/MM, respondeu: ...". Nenhum status muda —
 * a decisão é do coordenador.
 */
class SpeakerFreeTextHandler implements InboundHandler
{
    /**
     * Notificação "viva" = enviada há no máximo isto. Cobre o intervalo
     * designação → lembrete → fim de semana do discurso.
     */
    protected const LIVE_NOTIFICATION_DAYS = 45;

    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        return $this->notificationFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $notification = $this->notificationFor($message);

        if ($notification === null) {
            $message->markUnhandled();

            return;
        }

        $assignment = $notification->assignment;
        $team = $assignment->team;
        $speaker = $notification->speaker?->name ?? __('desconhecido');
        $date = $assignment->date->translatedFormat('d/m');
        $body = $message->text ?? '['.$message->type.']';

        $this->alert->send($team, "o orador {$speaker}, discurso de {$date}, respondeu: {$body}");

        $responsiblePhone = Phone::normalize($this->responsible->for($team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * The most recent live notification sent to a speaker with the sender's
     * phone, when any.
     */
    protected function notificationFor(WhatsAppInboundMessage $message): ?TalkAssignmentNotification
    {
        $phone = Phone::normalize($message->wa_id);

        if ($phone === null) {
            return null;
        }

        return TalkAssignmentNotification::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(self::LIVE_NOTIFICATION_DAYS))
            ->whereHas('speaker', fn ($query) => $query->where('phone', $phone))
            ->with(['assignment.team', 'speaker'])
            ->orderByDesc('sent_at')
            ->first();
    }
}
