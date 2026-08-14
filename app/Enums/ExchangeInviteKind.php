<?php

namespace App\Enums;

/**
 * Variante do convite mensal enviado à congregação parceira.
 *
 * `Exchange` quando temos oradores livres para oferecer em troca; `Help`
 * quando o mês está sem orador disponível e o convite vira um pedido de
 * ajuda para completar o arranjo.
 */
enum ExchangeInviteKind: string
{
    case Exchange = 'exchange';
    case Help = 'help';

    /**
     * Get the config key of the template used for this kind.
     */
    public function templateKey(): string
    {
        return match ($this) {
            self::Exchange => 'exchange_invite',
            self::Help => 'exchange_help',
        };
    }

    /**
     * The quick-reply label that accepts this invite variant.
     */
    public function acceptLabel(): string
    {
        return match ($this) {
            self::Exchange => 'Sim, vamos combinar',
            self::Help => 'Podemos ajudar',
        };
    }

    /**
     * The quick-reply label that declines the invite (same on both variants).
     */
    public function declineLabel(): string
    {
        return 'Este mês não';
    }
}
