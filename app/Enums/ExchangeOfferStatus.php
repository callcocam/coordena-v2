<?php

namespace App\Enums;

/**
 * Situação da oferta de orador dentro de um envio.
 */
enum ExchangeOfferStatus: string
{
    case Draft = 'draft';
    case Selected = 'selected';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Confirmed = 'confirmed';
    case Discarded = 'discarded';
}
