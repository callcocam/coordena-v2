<?php

namespace App\Enums;

/**
 * Situação da apresentação (primeiro contato) enviada a uma congregação.
 *
 * `pending → sent` no envio; `sent → accepted|declined` pela resposta dos
 * botões; `failed` quando a entrega esgota as tentativas; `expired` fica
 * reservado para apresentações sem resposta (fechamento manual/futuro).
 * Uma recusa reativada volta a `accepted` com `reactivated_at` preenchido.
 */
enum CongregationIntroStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
}
