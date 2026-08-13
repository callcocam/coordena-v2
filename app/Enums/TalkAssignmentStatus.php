<?php

namespace App\Enums;

/**
 * Situação do discurso na programação.
 */
enum TalkAssignmentStatus: string
{
    case Open = 'open';
    case Scheduled = 'scheduled';
    case Notified = 'notified';
    case Confirmed = 'confirmed';
    case NeedsReschedule = 'needs_reschedule';
}
