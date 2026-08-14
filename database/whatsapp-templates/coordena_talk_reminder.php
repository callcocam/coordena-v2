<?php

declare(strict_types=1);

/*
 * O repique da véspera para o orador. Quem dispara é o scheduler (fase 6) — o
 * template nasce na fase 3 para ir à aprovação da Meta junto com o aviso da
 * designação: aprovação leva dias, e submeter os dois de uma vez evita que a
 * fase 6 fique parada esperando a Meta.
 *
 * Mesmos rótulos do coordena_talk_assignment de propósito: é o mesmo fluxo que
 * trata a resposta na fase 4, e a correlação é pelo wamid, não pelo rótulo — o
 * orador que só confirma no lembrete confirma o mesmo discurso.
 *
 * Variáveis: {{1}} orador, {{2}} data do discurso, {{3}} congregação + horário
 * da reunião, {{4}} esboço (número e tema).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_talk_reminder', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! 🙏 Passando para lembrar do seu discurso em *{{2}}*, na congregação *{{3}}*.\n\nEsboço: *{{4}}*\n\nEstá tudo certo para você?",
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
