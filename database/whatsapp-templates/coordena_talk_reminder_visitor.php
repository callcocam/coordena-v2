<?php

declare(strict_types=1);

/*
 * O repique da véspera para o orador VISITANTE (Incoming): quem lembra é a
 * congregação anfitriã — "esperamos você" — porque é conosco que ele fala
 * neste número, não com a congregação dele.
 *
 * Quem dispara é o mesmo scheduler dos demais lembretes; a diferença é só a
 * copy. Mesmos rótulos do fluxo do orador de propósito: a resposta é tratada
 * pelo mesmo handler e correlacionada pelo wamid.
 *
 * Variáveis: {{1}} orador, {{2}} data do discurso, {{3}} congregação anfitriã
 * + horário da reunião, {{4}} esboço (número e tema).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_talk_reminder_visitor', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! 🙏 Esperamos você em *{{2}}* aqui na congregação *{{3}}*.\n\nEsboço: *{{4}}*\n\nEstá tudo certo para a sua visita?",
        [
            'João',
            'domingo, 12/07',
            'Central, às 09:30',
            'nº 88 — Como ter uma vida feliz',
        ],
    )
    ->quickReply('Tudo certo')
    ->quickReply('Preciso remarcar')
    ->toArray();
