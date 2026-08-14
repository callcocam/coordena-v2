<?php

declare(strict_types=1);

/*
 * A apresentação (primeiro contato) a uma congregação que ainda não conhece
 * o Coordena: quem somos + link da programação + pergunta de opt-in.
 *
 * A resposta vem pelos quick replies ("Sim, aceito" / "Agora não"), que o
 * IntroButtonHandler correlaciona pelo wamid. A Meta rejeita `\n` dentro de
 * variável e corpo terminando em variável, então o link entra no meio e a
 * pergunta fecha o corpo.
 *
 * Variáveis: {{1}} coordenador (quem escreve), {{2}} congregação-casa (com
 * cidade), {{3}} link público da programação.
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_intro', 'pt_BR', 'UTILITY')
    ->body(
        "Olá! Aqui é *{{1}}*, coordenador de discursos da congregação *{{2}}*. 🙏\n\nUsamos o Coordena para organizar a programação de discursos públicos e as trocas de oradores.\n\nVocê pode ver nossa programação aqui:\n{{3}}\n\nVocês teriam interesse em fazer trocas de oradores com a nossa congregação?",
        [
            'Carlos',
            'Capão Novo (Capão da Canoa RS)',
            'https://coordena.app/apresentacao/token-de-exemplo',
        ],
    )
    ->quickReply('Sim, aceito')
    ->quickReply('Agora não')
    ->toArray();
