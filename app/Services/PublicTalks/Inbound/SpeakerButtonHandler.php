<?php

namespace App\Services\PublicTalks\Inbound;

use App\Enums\SpeakerNotificationStatus;
use App\Enums\TalkAssignmentStatus;
use App\Jobs\SendSpeakerAssignmentNotification;
use App\Models\TalkAssignmentNotification;
use App\Services\PublicTalks\CoordinatorAlert;
use App\Services\PublicTalks\ResponsibleCoordinator;
use App\Support\Phone;
use Callcocam\WhatsAppCloud\Exceptions\CloudApiException;
use Callcocam\WhatsAppCloud\Facades\WhatsApp;
use Callcocam\WhatsAppCloud\Models\WhatsAppInboundMessage;
use Illuminate\Support\Facades\Log;

/**
 * Handler 1 — botão do orador, correlacionado por wamid.
 *
 * O quick reply do template chega com `context.id` apontando para o `wamid`
 * da notificação que enviamos ({@see SendSpeakerAssignmentNotification}).
 * É essa correlação — nunca o rótulo sozinho — que identifica o fluxo:
 *
 * - "Tudo certo"      → notificação `confirmed`, assignment `confirmed`,
 *   ack ao orador (sessão) e aviso ao coordenador.
 * - "Preciso remarcar" → notificação `reschedule_requested`, assignment
 *   `needs_reschedule`, ack ao orador e aviso ao coordenador — a decisão do
 *   que fazer é do coordenador, fora deste canal.
 *
 * Um botão com rótulo desconhecido (template futuro) não casa aqui e desce a
 * cadeia — tipicamente até o encaminhamento íntegro do handler 4.
 */
class SpeakerButtonHandler implements InboundHandler
{
    /**
     * Rótulos dos quick replies de `coordena_talk_assignment` e
     * `coordena_talk_reminder` (ver database/whatsapp-templates/README.md —
     * estes rótulos pertencem ao fluxo do orador).
     */
    protected const LABEL_CONFIRM = 'Tudo certo';

    protected const LABEL_RESCHEDULE = 'Preciso remarcar';

    public function __construct(
        protected CoordinatorAlert $alert,
        protected ResponsibleCoordinator $responsible,
    ) {}

    public function matches(WhatsAppInboundMessage $message): bool
    {
        if (! in_array($this->buttonText($message), [self::LABEL_CONFIRM, self::LABEL_RESCHEDULE], true)) {
            return false;
        }

        return $this->notificationFor($message) !== null;
    }

    public function handle(WhatsAppInboundMessage $message): void
    {
        $notification = $this->notificationFor($message);

        if ($notification === null) {
            $message->markUnhandled();

            return;
        }

        $confirmed = $this->buttonText($message) === self::LABEL_CONFIRM;
        $assignment = $notification->assignment;

        $notification->update([
            'status' => $confirmed ? SpeakerNotificationStatus::Confirmed : SpeakerNotificationStatus::RescheduleRequested,
            'responded_at' => now(),
            'response_payload' => [
                'wamid' => $message->wamid,
                'button' => $this->buttonText($message),
            ],
        ]);

        $assignment->update([
            'status' => $confirmed ? TalkAssignmentStatus::Confirmed : TalkAssignmentStatus::NeedsReschedule,
        ]);

        $this->acknowledgeSpeaker($message, $confirmed);

        $date = $assignment->date->translatedFormat('d/m');
        $speaker = $notification->speaker?->name ?? __('o orador');

        $this->alert->send($assignment->team, $confirmed
            ? "o orador {$speaker} confirmou o discurso de {$date}."
            : "o orador {$speaker} pediu para remarcar o discurso de {$date} — abra a programação para reprogramar.");

        $responsiblePhone = Phone::normalize($this->responsible->for($assignment->team)?->phone);

        $responsiblePhone === null
            ? $message->markUnhandled()
            : $message->markForwarded($responsiblePhone);
    }

    /**
     * Session ack to the speaker — his button tap just opened the 24h window.
     * Best effort: a failure here must not undo the state change.
     */
    protected function acknowledgeSpeaker(WhatsAppInboundMessage $message, bool $confirmed): void
    {
        $notification = $this->notificationFor($message);
        $team = $notification?->assignment->team;

        if ($team === null) {
            return;
        }

        $text = $confirmed
            ? 'Combinado! Obrigado por confirmar. 🙏'
            : 'Entendido! O coordenador vai falar com você para combinar outra data. 🙏';

        try {
            WhatsApp::for($team)->sendSessionText($message->wa_id, $text);
        } catch (CloudApiException $exception) {
            Log::warning('Speaker button ack not delivered.', [
                'wamid' => $message->wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The notification whose outbound wamid the reply quotes, when any.
     */
    protected function notificationFor(WhatsAppInboundMessage $message): ?TalkAssignmentNotification
    {
        if ($message->context_id === null) {
            return null;
        }

        return TalkAssignmentNotification::query()
            ->where('wamid', $message->context_id)
            ->with(['assignment.team', 'speaker'])
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
