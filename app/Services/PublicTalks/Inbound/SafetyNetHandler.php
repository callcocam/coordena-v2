<?php

namespace App\Services\PublicTalks\Inbound;

use App\Models\Team;
use App\Models\TeamWhatsappConnection;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handler 5 — rede de segurança: mensagem que nenhum fluxo reconheceu.
 *
 * Resolve o time pelo `phone_number_id` do webhook (conexão Cloud do time) e
 * encaminha um resumo ao responsável, com throttle por remetente para um
 * número desconhecido insistente não virar spam de alertas. Sem conexão que
 * identifique o time, a mensagem só fica registrada como não tratada.
 */
class SafetyNetHandler implements InboundHandler
{
    /**
     * No máximo um encaminhamento por remetente dentro desta janela.
     */
    protected const THROTTLE_SECONDS = 3600;

    /**
     * Tamanho máximo do trecho encaminhado no resumo.
     */
    protected const EXCERPT_LIMIT = 300;

    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        return $this->teamFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $team = $this->teamFor($message);

        if ($team === null) {
            $message->markUnhandled();

            return;
        }

        if (! Cache::add($this->throttleKey($team, $message), true, self::THROTTLE_SECONDS)) {
            $message->markUnhandled();

            return;
        }

        $sender = $message->contact_name ?? $message->wa_id;
        $excerpt = Str::limit($message->text ?? '['.$message->type.']', self::EXCERPT_LIMIT);

        $this->alert->send($team, "mensagem não reconhecida de {$sender} ({$message->wa_id}) no WhatsApp: {$excerpt}");

        $responsiblePhone = Phone::normalize($this->responsible->for($team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
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
     * One forward per sender per team inside the throttle window.
     */
    protected function throttleKey(Team $team, WhatsAppInboundMessage $message): string
    {
        return "whatsapp-inbound:safety-net:{$team->id}:{$message->wa_id}";
    }
}
