<?php

namespace App\Enums;

/**
 * Situação de um envio do convite a uma congregação.
 */
enum ExchangeInviteSendStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Answered = 'answered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
}
