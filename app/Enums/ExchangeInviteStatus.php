<?php

namespace App\Enums;

/**
 * Situação do convite mensal de permuta.
 */
enum ExchangeInviteStatus: string
{
    case Open = 'open';
    case PartiallyFilled = 'partially_filled';
    case Filled = 'filled';
    case Expired = 'expired';
}
