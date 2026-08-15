<?php

namespace App\Enums;

/**
 * Tipo de notificação enviada ao orador de um discurso.
 */
enum SpeakerNotificationKind: string
{
    case Assignment = 'assignment';
    case Reminder = 'reminder';
}
