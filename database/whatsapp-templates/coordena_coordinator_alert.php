<?php

declare(strict_types=1);

/*
 * Alerta genérico ao coordenador responsável (e ajudantes) fora da janela de
 * 24h: o orador respondeu, um convite foi aceito, algo mudou na programação.
 *
 * O coordenador responde quando dá — de madrugada, no domingo à noite — e não
 * há ninguém logado para ver a tela mudar. Vai como template porque o
 * coordenador também não tem janela de 24h aberta conosco (a sessão do orador
 * é dele, não do coordenador). Quando a janela do coordenador está aberta, o
 * helper manda mensagem de sessão e este template nem é usado.
 *
 * Sem botões: não há decisão a tomar por aqui — o corpo aponta para a tela.
 *
 * Variáveis: {{1}} coordenador, {{2}} o aviso em uma frase curta (uma linha,
 * sem quebra — a Meta rejeita `\n` dentro de variável).
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_coordinator_alert', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! Aviso do Coordena sobre os discursos públicos: {{2}}\n\nQuando puder, abra a programação para conferir os detalhes.",
        [
            'Carlos',
            'o orador João confirmou o discurso de domingo, 12/07.',
        ],
    )
    ->toArray();
