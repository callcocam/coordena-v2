<?php

declare(strict_types=1);

/*
 * O aviso ao ORADOR do discurso que já está na programação, pedindo que ele
 * confirme se continua de pé.
 *
 * Não é aqui que o discurso se combina: quando esta mensagem sai, data, esboço
 * e congregação já estão definidos e o slot já está na grade. O orador só diz
 * "tudo certo" ou avisa que precisa remarcar — a decisão do que fazer nesse
 * caso é do coordenador, fora deste canal.
 *
 * Vai como template porque o orador quase nunca tem janela de 24h aberta
 * conosco: ele é contato de outra congregação e só fala com este número quando
 * há discurso na agenda. O template abre a conversa e, pelo quick reply, abre
 * também a janela — é por ela que a resposta em texto livre dele chega (fase 4).
 *
 * Os rótulos "Tudo certo"/"Preciso remarcar" pertencem a este fluxo (aviso e
 * lembrete ao orador). Ver a tabela de propriedade de rótulos no README.md.
 *
 * Variáveis: {{1}} orador, {{2}} data do discurso, {{3}} congregação + horário
 * da reunião, {{4}} esboço (número e tema), {{5}} referência (link do esboço ou
 * a frase de fallback quando o catálogo não tem link).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_talk_assignment', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! 🙏 Passando para confirmar o seu discurso público de *{{2}}*, na congregação *{{3}}*.\n\nEsboço: *{{4}}*\n{{5}}\n\nEstá tudo certo para essa data? Se surgiu algum imprevisto, é só me avisar que o coordenador fala com você.",
        [
            'João',
            'domingo, 12/07',
            'Central, às 09:30',
            'nº 88 — Como ter uma vida feliz',
            'https://wol.jw.org/pt/esbocos/88',
        ],
    )
    ->quickReply('Tudo certo')
    ->quickReply('Preciso remarcar')
    ->toArray();
