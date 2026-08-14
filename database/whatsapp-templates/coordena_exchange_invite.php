<?php

declare(strict_types=1);

/*
 * O convite de permuta à congregação parceira: mês + link do portal.
 *
 * Curto de propósito: o conteúdo rico (semanas em aberto, oradores) vai pela
 * mensagem de sessão quando a janela de 24h estiver aberta, ou pelo portal —
 * o template é só o abridor. A Meta rejeita `\n` dentro de variável e corpo
 * terminando em variável, então o link entra no meio e o fecho é fixo.
 *
 * Sem botões: a resposta da parceira acontece no portal (portal_token), não
 * por quick reply — o portal registra ofertas sem depender da janela.
 *
 * Variáveis: {{1}} contato (nome de quem recebe), {{2}} congregação (nossa),
 * {{3}} mês por extenso, {{4}} link do portal da permuta.
 */

use Callcocam\WhatsAppCloud\Templates\TemplateBuilder;

return TemplateBuilder::make('coordena_exchange_invite', 'pt_BR', 'UTILITY')
    ->body(
        "Olá, *{{1}}*! Aqui é da congregação *{{2}}*. 🙏\n\nEstamos montando a programação de discursos públicos de *{{3}}* e gostaríamos de combinar uma permuta de oradores com vocês.\n\nAs datas em aberto e os detalhes estão neste link:\n{{4}}\n\nSe der para participar, é só responder por aqui ou pelo próprio link.",
        [
            'Carlos',
            'Central',
            'julho de 2026',
            'https://coordena.app/permuta/token-de-exemplo',
        ],
    )
    ->toArray();
