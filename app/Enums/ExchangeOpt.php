<?php

namespace App\Enums;

/**
 * Disposição da congregação parceira quanto a permutas.
 */
enum ExchangeOpt: string
{
    case OptedIn = 'opted_in';
    case OptedOut = 'opted_out';
    case Unknown = 'unknown';
}
