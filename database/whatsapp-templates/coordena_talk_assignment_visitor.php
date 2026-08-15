<?php

declare(strict_types=1);

/*
 * O aviso ao orador VISITANTE (Incoming): quem fala é a congregação que vai
 * RECEBER o discurso, não a dele. A diferença de voz em relação ao
 * coordena_talk_assignment é proposital — "aqui na congregação" deixa claro
 * que ele vem até nós, e o convite já traz onde e a que horas ele é esperado.
 *
 * Como no aviso do orador local, nada se combina aqui: a troca já foi aceita
 * pelos coordenadores e o slot já está na grade. Ele só confirma presença ou
 * pede para remarcar — e remarcar volta para os coordenadores das DUAS
 * congregações, fora deste canal.
 *
 * Mesmos rótulos "Tudo certo"/"Preciso remarcar" do fluxo do orador: a
 * resposta é tratada pelo mesmo handler e correlacionada pelo wamid.
 *
 * Variáveis: {{1}} orador, {{2}} data do discurso, {{3}} congregação anfitriã
 * + horário da reunião, {{4}} esboço (número e tema), {{5}} referência (link
 * do esboço ou a frase de fallback quando o catálogo não tem link).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_talk_assignment_visitor', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! 🙏 Será um prazer receber você aqui na congregação *{{3}}* para o discurso público de *{{2}}*.\n\nEsboço: *{{4}}*\n{{5}}\n\nPodemos confirmar a sua visita? Se surgiu algum imprevisto, é só me avisar que os coordenadores combinam entre si.",
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
