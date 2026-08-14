<?php

namespace App\Enums;

/**
 * Tipo de notificação enviada ao orador de um discurso.
 */
enum SpeakerNotificationKind: string
{
    case Assignment = 'assignment';
    case Reminder = 'reminder';

    /**
     * Get the config key of the template used for this kind.
     */
    public function templateKey(): string
    {
        return match ($this) {
            self::Assignment => 'talk_assignment',
            self::Reminder => 'talk_reminder',
        };
    }
}
