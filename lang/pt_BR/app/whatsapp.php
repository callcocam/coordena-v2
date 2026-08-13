<?php

declare(strict_types=1);

return [

    // WhatsApp oficial (Meta Cloud API) por time → app.whatsapp.*

    'status' => [
        'disconnected' => 'Não configurado',
        'connected' => 'Configurado',
    ],

    'errors' => [
        'not_connected' => 'O WhatsApp oficial deste time ainda não está configurado.',
        'api' => 'A API do WhatsApp retornou um erro: :message',
        'terms_required' => 'É necessário aceitar o termo de uso antes de ativar o WhatsApp.',
        'api_disabled' => 'Este time está em modo manual: a API do WhatsApp está desativada.',
    ],

    // Modo de operação: usar a API do WhatsApp (automático) ou modo manual.
    'mode' => [
        'title' => 'Modo de envio',
        'toggle' => 'Usar a API do WhatsApp (envios automáticos)',
        'toggle_help' => 'Quando ligado, o sistema envia as mensagens pela API oficial da Meta. Quando desligado, você faz os envios manualmente (copiar mensagem, mandar pelo WhatsApp, compartilhar PDF por link).',
        'manual_title' => 'Modo manual',
        'manual_description' => 'A API do WhatsApp está desligada. Você fará os envios manualmente: copie as mensagens e os links e envie pelo seu WhatsApp.',
        'saved' => 'Modo de envio atualizado.',
        'copy_link' => 'Copiar link',
        'link_copied' => 'Link copiado! Cole e envie pelo seu WhatsApp.',
        'message_copied' => 'Mensagem copiada! Cole e envie pelo seu WhatsApp.',
        'link_failed' => 'Não foi possível gerar o link.',
        'link_event_required' => 'Selecione um evento para gerar o link de disponibilidade.',
    ],

    // WhatsApp oficial (Meta Cloud API) — único provedor de mensagens.
    'cloud' => [
        'title' => 'WhatsApp oficial (Meta)',
        'description' => 'Envie as mensagens pela API oficial da Meta (WhatsApp Cloud API).',
        'help' => 'Cole o Phone Number ID, o WABA ID e o token permanente (System User) obtidos no Business Manager da Meta.',
        'phone_number_id' => 'Phone Number ID',
        'waba_id' => 'WhatsApp Business Account ID (WABA)',
        'app_id' => 'App ID',
        'token' => 'Token de acesso permanente',
        'verified_name' => 'Nome verificado',
        'save' => 'Salvar credenciais',
        'saved' => 'WhatsApp oficial (Meta) configurado.',
        'active' => 'Meta ativo',
        'configured' => 'Configurado',
        'not_configured' => 'Ainda não configurado',
        'shared' => 'Número compartilhado',
        'shared_title' => 'Usando o número compartilhado Coordena',
        'shared_description' => 'Os envios desta equipe saem pelo número oficial do Coordena, compartilhado entre as equipes. Cada equipe fala apenas com os seus próprios contatos — você não precisa configurar nada.',
        'not_configured_help' => 'O número oficial ainda não foi configurado pelo administrador do Coordena. Assim que estiver ativo, os envios desta equipe passam a funcionar automaticamente.',
        'own_number' => 'Usar um número próprio (opcional)',
        'tier' => 'Limite de mensagens',
        'quality' => 'Qualidade',
    ],

    'card' => [
        'title' => 'WhatsApp do time',
        'description' => 'Configure o número oficial (Meta Cloud API) para enviar mensagens aos voluntários.',
        'connected_as' => 'Nome verificado: :name',
        'manage_forbidden' => 'Só os coordenadores do time podem gerenciar a conexão do WhatsApp.',
    ],

    'terms' => [
        'title' => 'Termo de uso e responsabilidade',
        'intro' => 'Antes de ativar o envio pela API oficial da Meta, leia e aceite as condições abaixo. Ao aceitar, você assume a responsabilidade pelo uso e pelas mensagens que a sua equipe enviar.',

        // Destaque principal — número compartilhado e responsabilidade da própria equipe.
        'new_number_title' => 'Você responde pelas mensagens da sua equipe',
        'new_number' => 'Os envios saem pelo número oficial do Coordena, compartilhado entre as equipes. Cada equipe fala apenas com os seus próprios contatos — você responde só pelas mensagens que a sua equipe enviar e por contatar só quem consentiu (LGPD). Se a sua equipe tiver um número próprio na Meta, dá para configurar depois (opcional).',

        // Itens do termo — apenas fatos verificáveis, sem regras inventadas.
        'items' => [
            'API oficial: o sistema envia as mensagens pela WhatsApp Cloud API da Meta, usando templates aprovados. Mensagens fora dos templates só são possíveis dentro da janela de 24h após a pessoa responder.',
            'Responsabilidade pelo envio: você responde pelas mensagens que a SUA equipe enviar, pelo conteúdo delas e por só contatar pessoas que consentiram em receber mensagens (LGPD). Cada equipe envia apenas para os seus próprios contatos; você não responde pelo que outras equipes enviarem.',
            'Políticas da Meta: o uso deve seguir as políticas do WhatsApp Business. A Meta pode restringir ou limitar o número (qualidade/tier) conforme o uso e as denúncias dos destinatários.',
            'Sem garantias: o sistema e seus mantenedores não garantem entrega das mensagens nem disponibilidade da API, e não se responsabilizam por limitações, perdas ou consequências decorrentes do uso.',
        ],

        'record_notice' => 'Ao concordar, registramos a data e hora, o seu usuário e o endereço IP como comprovação do aceite.',

        'read' => 'Ler o termo completo',
        'checkbox' => 'Li e concordo com o termo de uso e responsabilidade.',
        'agree' => 'Concordar e continuar',
        'accepted_at' => 'Termo aceito por você.',
    ],

    'alert' => [
        'title' => 'WhatsApp não configurado',
        'description' => 'Configure o WhatsApp oficial deste time para enviar mensagens.',
        'description_member' => 'O WhatsApp deste time ainda não está configurado. Fale com um coordenador para configurar.',
        'cta' => 'Configurar WhatsApp',
    ],

    'toast' => [
        'saved' => 'WhatsApp configurado.',
    ],
];
