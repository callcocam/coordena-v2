<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| app.marketing.* — página inicial (landing) e demais telas de divulgação
|--------------------------------------------------------------------------
*/

return [
    'welcome' => [
        'meta_title' => 'Bem-vindo',
        'nav' => [
            'dashboard' => 'Painel',
            'login' => 'Entrar',
            'register' => 'Cadastre-se',
        ],
        'get_started' => [
            'title' => 'Vamos começar',
            'description' => 'O Laravel tem um ecossistema incrivelmente rico. Sugerimos começar pelos itens a seguir.',
            'docs_prefix' => 'Leia a',
            'docs_link' => 'Documentação',
            'laracasts_prefix' => 'Assista a tutoriais em vídeo no',
            'laracasts_link' => 'Laracasts',
            'deploy' => 'Fazer deploy agora',
        ],
    ],
];
