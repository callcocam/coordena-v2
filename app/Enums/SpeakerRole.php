<?php

namespace App\Enums;

/**
 * Privilégio do orador no acervo.
 */
enum SpeakerRole: string
{
    case Elder = 'elder';
    case MinisterialServant = 'ministerial_servant';
    case Other = 'other';
}
