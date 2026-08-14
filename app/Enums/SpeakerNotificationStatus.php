<?php

namespace App\Enums;

/**
 * Situação de uma notificação enviada ao orador.
 *
 * `Pending` é o registro criado antes do envio; `Sent` tem `wamid` gravado.
 * `Confirmed`/`RescheduleRequested` chegam pela resposta do orador (fase 4).
 */
enum SpeakerNotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Confirmed = 'confirmed';
    case RescheduleRequested = 'reschedule_requested';

    /**
     * Whether the speaker still has not answered this notification.
     */
    public function isAwaitingReply(): bool
    {
        return $this === self::Sent;
    }
}
