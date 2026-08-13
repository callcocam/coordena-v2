<?php

declare(strict_types=1);

return [

    // Página pública de apresentação (Welcome) → app.marketing.*

    'meta_title' => 'Coordena — Escalas e voluntários sem bagunça',
    'meta_description' => 'O Coordena organiza a escala da sua equipe, reúne os voluntários e confirma a presença de todos pelo WhatsApp. Feito para usar no celular.',

    'nav' => [
        'dashboard' => 'Ir para o painel',
        'login' => 'Entrar',
        'register' => 'Criar conta',
        'features' => 'Recursos',
        'how' => 'Como funciona',
        'skip' => 'Pular para o conteúdo',
    ],

    'theme' => [
        'toggle' => 'Alternar tema claro e escuro',
    ],

    'hero' => [
        'eyebrow' => 'Coordenação de voluntários',
        'title' => 'A escala da sua equipe,',
        'title_highlight' => 'confirmada no WhatsApp',
        'description' => 'Monte a programação, distribua as funções e saiba quem confirmou presença — tudo em um só lugar, direto do celular.',
        'cta_primary' => 'Começar agora',
        'cta_secondary' => 'Já tenho conta',
        'note' => 'Grátis para começar. Sem cartão de crédito.',
    ],

    // Cartão-assinatura do hero: uma escala "ao vivo".
    'hero_card' => [
        'label' => 'Escala do sábado',
        'date' => '12 de julho · 19h',
        'confirmed_summary' => '3 de 4 confirmados',
        'status_confirmed' => 'Confirmado',
        'status_pending' => 'Aguardando',
        'roles' => [
            'reception' => 'Recepção',
            'sound' => 'Som',
            'cleaning' => 'Limpeza',
            'parking' => 'Estacionamento',
        ],
        'people' => [
            'ana' => 'Ana',
            'bruno' => 'Bruno',
            'carla' => 'Carla',
            'diego' => 'Diego',
        ],
        'whatsapp_hint' => 'Confirmações recebidas pelo WhatsApp',
    ],

    'stats' => [
        'items' => [
            'time' => ['value' => '5 min', 'label' => 'para montar uma escala'],
            'whatsapp' => ['value' => 'WhatsApp', 'label' => 'confirmação automática'],
            'mobile' => ['value' => '100%', 'label' => 'pensado para o celular'],
        ],
    ],

    'features' => [
        'eyebrow' => 'Tudo em um só lugar',
        'title' => 'O que o Coordena faz por você',
        'subtitle' => 'Menos planilhas e mensagens soltas. Mais organização.',
        'items' => [
            'events' => [
                'title' => 'Eventos e programações',
                'body' => 'Crie reuniões, cultos e mutirões com data, horário e funções necessárias.',
            ],
            'volunteers' => [
                'title' => 'Cadastro de voluntários',
                'body' => 'Reúna sua equipe com nome e contato. Importe uma lista pronta em segundos.',
            ],
            'teams' => [
                'title' => 'Equipes e congregações',
                'body' => 'Separe por equipe ou congregação e defina quem coordena cada grupo.',
            ],
            'whatsapp' => [
                'title' => 'Confirmação pelo WhatsApp',
                'body' => 'Envie o convite e receba a resposta de cada pessoa sem sair da conversa.',
            ],
        ],
    ],

    'steps' => [
        'eyebrow' => 'Como funciona',
        'title' => 'Da lista à presença confirmada',
        'subtitle' => 'Três passos para tirar a escala do papel.',
        'items' => [
            'one' => [
                'title' => 'Cadastre os voluntários',
                'body' => 'Adicione as pessoas da sua equipe ou importe uma lista que você já tem.',
            ],
            'two' => [
                'title' => 'Monte a escala',
                'body' => 'Escolha o evento, as funções e quem vai atender em cada uma.',
            ],
            'three' => [
                'title' => 'Confirme pelo WhatsApp',
                'body' => 'O Coordena avisa cada voluntário e mostra quem já confirmou presença.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Pronto para organizar sua próxima escala?',
        'subtitle' => 'Crie sua conta gratuita e monte a primeira programação hoje.',
        'button' => 'Criar conta gratuita',
        'secondary' => 'Entrar na minha conta',
    ],

    'footer' => [
        'tagline' => 'Coordene equipes, eventos e voluntários em um só lugar.',
        'product_title' => 'Produto',
        'legal_title' => 'Legal',
        'help' => 'Central de Ajuda',
        'terms' => 'Termos de Uso',
        'privacy' => 'Política de Privacidade',
        'rights' => 'Todos os direitos reservados.',
        'made_in' => 'Feito no Brasil.',
    ],
];
