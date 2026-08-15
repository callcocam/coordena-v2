<?php

declare(strict_types=1);

/*
 * O repique da véspera para o NOSSO orador que SAI (Outgoing): o discurso é
 * em outra congregação, então o lembrete reforça o destino e o horário — é o
 * detalhe que ele mais esquece quando a semana é fora de casa.
 *
 * Quem dispara é o mesmo scheduler dos demais lembretes; a diferença é só a
 * copy. Mesmos rótulos do fluxo do orador de propósito: a resposta é tratada
 * pelo mesmo handler e correlacionada pelo wamid.
 *
 * Variáveis: {{1}} orador, {{2}} data do discurso, {{3}} congregação de
 * destino + horário da reunião, {{4}} esboço (número e tema).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_talk_reminder_out', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! 🙏 Lembrete do seu discurso em *{{2}}* — desta vez você visita a congregação *{{3}}*.\n\nEsboço: *{{4}}*\n\nEstá tudo certo para a viagem?",
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
