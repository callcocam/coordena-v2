<?php

declare(strict_types=1);

/*
 * O abridor do convite de troca à congregação parceira (v2, conversacional).
 *
 * Curtíssimo de propósito: cada template business-initiated abre uma conversa
 * cobrada na Meta, então TODO o conteúdo rico (semanas em aberto, oradores,
 * link do portal) só vai como mensagem de sessão DEPOIS que o convidado
 * responde — o quick reply abre a janela de 24h gratuita.
 *
 * Antes do aceite não expomos oradores: o template diz apenas QUANTOS temos
 * ({{4}}), nunca quem. Quando não há orador livre no mês, o envio usa a
 * variante `coordena_exchange_help` em vez deste template.
 *
 * A Meta rejeita `\n` dentro de variável e corpo terminando em variável, por
 * isso o fecho "Podemos combinar?" é fixo. {{4}} carrega a frase completa
 * ("3 oradores"/"1 orador") para o plural ficar correto sem lógica na Meta.
 *
 * Os rótulos "Sim, vamos combinar"/"Este mês não" pertencem a este fluxo
 * (convite de troca). Ver a tabela de propriedade de rótulos no README.md.
 *
 * Variáveis: {{1}} contato (nome de quem recebe), {{2}} congregação (nossa),
 * {{3}} mês por extenso, {{4}} contagem de oradores ("3 oradores").
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_exchange_invite', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! Aqui é da congregação *{{2}}*. 🙏\n\nEstamos montando a programação de discursos públicos de *{{3}}* e temos *{{4}}* disponíveis para uma troca com vocês.\n\nPodemos combinar?",
        [
            'Carlos',
            'Central',
            'julho de 2026',
            '3 oradores',
        ],
    )
    ->quickReply('Sim, vamos combinar')
    ->quickReply('Este mês não')
    ->toArray();
