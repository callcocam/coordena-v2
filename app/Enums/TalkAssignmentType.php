<?php

namespace App\Enums;

/**
 * Tipo do discurso no fim de semana do time.
 */
enum TalkAssignmentType: string
{
    case Home = 'home';
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';
}
