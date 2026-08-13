<?php

namespace App\Enums;

/**
 * Situação do esboço no catálogo oficial (S-99).
 */
enum PublicTalkOutlineStatus: string
{
    case Active = 'active';
    case Replaced = 'replaced';
    case Discontinued = 'discontinued';
}
