<?php

declare(strict_types=1);

/*
 * A variante "pedido de ajuda" do convite mensal: usada quando
 * SpeakerAvailability::availableFor(casa, mês) está vazio — sem orador livre,
 * a mensagem não pode ser de troca, é um pedido para completar o arranjo.
 *
 * Mesma economia do abridor de troca: template curtíssimo, sem link e sem
 * nomes; o conteúdo rico (semanas em aberto + link do portal + sugestão de
 * contato direto com os oradores deles) vai por sessão após o aceite.
 *
 * Os rótulos "Podemos ajudar"/"Este mês não" pertencem ao fluxo do convite
 * de troca. Ver a tabela de propriedade de rótulos no README.md.
 *
 * Variáveis: {{1}} contato (nome de quem recebe), {{2}} congregação (nossa),
 * {{3}} mês por extenso.
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_exchange_help', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! Aqui é da congregação *{{2}}*. 🙏\n\nEstamos completando a programação de discursos públicos de *{{3}}* e neste mês não temos oradores para oferecer em troca.\n\nVocês poderiam nos ajudar enviando um orador?",
        [
            'Carlos',
            'Central',
            'julho de 2026',
        ],
    )
    ->quickReply('Podemos ajudar')
    ->quickReply('Este mês não')
    ->toArray();
