<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Grupo "app.public_talks" — arranjo de oradores
|--------------------------------------------------------------------------
*/

return [
    'nav' => [
        'schedule' => 'Discursos',
        'acervo' => 'Acervo',
        'coordinators' => 'Coordenadores',
    ],

    'schedule' => [
        'title' => 'Discursos públicos',
        'breadcrumb' => 'Discursos',
        'description' => 'Programação de fins de semana da congregação :name.',
        'pending_badge' => ':count pendente|:count pendentes',
        'empty' => 'Nenhuma semana no horizonte para este mês.',
        'edit_slot' => 'Preencher semana',
        'sheet_title' => 'Semana de :date',
        'sheet_description' => 'Escolha o orador e o esboço para esta semana.',
        'speaker_label' => 'Orador',
        'speaker_placeholder' => 'Selecione um orador',
        'outline_label' => 'Esboço',
        'outline_placeholder' => 'Selecione um esboço',
        'outline_prepared' => 'preparado',
        'unavailable' => 'indisponível no mês',
        'no_speaker' => 'Sem orador definido',
        'clear' => 'Limpar semana',
        'save' => 'Salvar',
        'types' => [
            'home' => 'Na congregação',
            'incoming' => 'Orador visitante',
            'outgoing' => 'Fora (permuta)',
        ],
        'statuses' => [
            'open' => 'Em aberto',
            'scheduled' => 'Programado',
            'notified' => 'Notificado',
            'confirmed' => 'Confirmado',
            'needs_reschedule' => 'Reprogramar',
        ],
    ],

    'setup' => [
        'title' => 'Configurar discursos públicos',
        'congregation_title' => 'Congregação da casa',
        'congregation_description' => 'Escolha uma congregação do acervo ou cadastre a sua para liberar a programação.',
        'existing_label' => 'Usar congregação do acervo',
        'existing_placeholder' => 'Selecione uma congregação',
        'or_create' => 'Ou cadastre uma nova',
        'name_label' => 'Nome da congregação',
        'city_label' => 'Cidade',
        'weekday_label' => 'Dia da reunião',
        'time_label' => 'Horário da reunião',
        'coordinator_title' => 'Coordenador responsável',
        'coordinator_description' => 'Informe quem coordena os discursos públicos para liberar a programação.',
        'coordinator_name_label' => 'Nome do coordenador',
        'coordinator_phone_label' => 'Telefone (WhatsApp)',
        'continue' => 'Continuar',
        'finish' => 'Concluir configuração',
    ],

    'coordinators' => [
        'title' => 'Coordenadores de discursos',
        'breadcrumb' => 'Coordenadores',
        'description' => 'Responsável e ajudantes do arranjo de oradores.',
        'add' => 'Adicionar coordenador',
        'edit_title' => 'Editar coordenador',
        'create_title' => 'Novo coordenador',
        'name_label' => 'Nome',
        'phone_label' => 'Telefone (WhatsApp)',
        'role_label' => 'Função',
        'active_label' => 'Ativo',
        'inactive' => 'Inativo',
        'delete_title' => 'Remover coordenador',
        'delete_description' => 'Tem certeza que deseja remover :name?',
        'empty' => 'Nenhum coordenador cadastrado.',
        'roles' => [
            'responsible' => 'Responsável',
            'helper' => 'Ajudante',
        ],
    ],

    'congregations' => [
        'title' => 'Acervo de congregações',
        'breadcrumb' => 'Acervo',
        'description' => 'Congregações e oradores compartilhados entre os seus times.',
        'search_placeholder' => 'Buscar por nome, cidade ou circuito',
        'add' => 'Nova congregação',
        'edit' => 'Editar congregação',
        'home_badge' => 'Congregação da casa',
        'speakers_count' => ':count orador|:count oradores',
        'empty' => 'Nenhuma congregação no acervo.',
        'name_label' => 'Nome',
        'city_label' => 'Cidade',
        'circuit_label' => 'Circuito',
        'address_label' => 'Endereço',
        'contact_name_label' => 'Contato',
        'contact_phone_label' => 'Telefone do contato',
        'contact_email_label' => 'E-mail do contato',
        'secretary_name_label' => 'Secretário',
        'secretary_phone_label' => 'Telefone do secretário',
        'secretary_email_label' => 'E-mail do secretário',
        'weekday_label' => 'Dia da reunião',
        'time_label' => 'Horário da reunião',
        'contact_title' => 'Contato',
        'secretary_title' => 'Secretário',
        'delete_title' => 'Remover congregação',
        'delete_description' => 'Tem certeza que deseja remover :name do acervo?',
    ],

    'speakers' => [
        'title' => 'Oradores',
        'description' => 'Oradores desta congregação e os esboços que têm preparados.',
        'add' => 'Novo orador',
        'edit_title' => 'Editar orador',
        'create_title' => 'Novo orador',
        'name_label' => 'Nome',
        'phone_label' => 'Telefone (WhatsApp)',
        'role_label' => 'Privilégio',
        'active_label' => 'Ativo',
        'inactive' => 'Inativo',
        'notes_label' => 'Observações',
        'outlines_label' => 'Esboços preparados',
        'outlines_count' => ':count esboço|:count esboços',
        'empty' => 'Nenhum orador nesta congregação.',
        'delete_title' => 'Remover orador',
        'delete_description' => 'Tem certeza que deseja remover :name?',
        'roles' => [
            'elder' => 'Ancião',
            'ministerial_servant' => 'Servo ministerial',
            'other' => 'Outro',
        ],
    ],

    'weekdays' => [
        '0' => 'Domingo',
        '1' => 'Segunda-feira',
        '2' => 'Terça-feira',
        '3' => 'Quarta-feira',
        '4' => 'Quinta-feira',
        '5' => 'Sexta-feira',
        '6' => 'Sábado',
    ],

    'common' => [
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'remove' => 'Remover',
    ],
];
