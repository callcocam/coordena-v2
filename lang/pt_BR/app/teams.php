<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| app.teams.* — listagem, edição, convites, membros e troca de equipe
|--------------------------------------------------------------------------
*/

return [
    'index' => [
        'breadcrumb' => 'Equipes',
        'meta_title' => 'Equipes',
        'title' => 'Equipes',
        'description' => 'Gerencie suas equipes e participações',
        'new_team' => 'Nova equipe',
        'personal' => 'Pessoal',
        'view_team' => 'Ver equipe',
        'edit_team' => 'Editar equipe',
        'empty' => 'Você ainda não faz parte de nenhuma equipe.',
    ],
    'edit' => [
        'title_edit' => 'Editar :name',
        'title_view' => 'Ver :name',
        'settings_title' => 'Configurações da equipe',
        'settings_description' => 'Atualize o nome e as configurações da equipe',
        'name_label' => 'Nome da equipe',
    ],
    'create' => [
        'title' => 'Criar uma nova equipe',
        'description' => 'Crie uma nova equipe para colaborar com outras pessoas.',
        'name_label' => 'Nome da equipe',
        'name_placeholder' => 'Minha equipe',
        'submit' => 'Criar equipe',
    ],
    'delete' => [
        'title' => 'Excluir equipe',
        'description' => 'Excluir permanentemente sua equipe',
        'warning' => 'Atenção',
        'warning_text' => 'Prossiga com cautela, esta ação não pode ser desfeita.',
        'button' => 'Excluir equipe',
        'confirm_title' => 'Tem certeza?',
        'confirm_description' => 'Esta ação não pode ser desfeita. Isso excluirá permanentemente a equipe',
        'confirm_type_prefix' => 'Digite',
        'confirm_type_suffix' => 'para confirmar',
        'name_placeholder' => 'Informe o nome da equipe',
    ],
    'invite' => [
        'title' => 'Convidar um membro',
        'description' => 'Envie um convite para participar desta equipe.',
        'email_label' => 'E-mail',
        'email_placeholder' => 'colega@exemplo.com',
        'role_label' => 'Função',
        'role_placeholder' => 'Selecione uma função',
        'submit' => 'Enviar convite',
    ],
    'members' => [
        'title' => 'Membros da equipe',
        'description' => 'Gerencie quem faz parte desta equipe',
        'invite' => 'Convidar membro',
        'no_cargo' => 'Sem cargo',
        'remove_tooltip' => 'Remover membro',
        'remove_title' => 'Remover membro da equipe',
        'remove_confirm_prefix' => 'Tem certeza de que deseja remover',
        'remove_confirm_suffix' => 'desta equipe?',
        'remove_button' => 'Remover membro',
    ],
    'invitations' => [
        'title' => 'Convites pendentes',
        'description' => 'Convites que ainda não foram aceitos',
        'cancel_tooltip' => 'Cancelar convite',
        'cancel_title' => 'Cancelar convite',
        'cancel_confirm_prefix' => 'Tem certeza de que deseja cancelar o convite para',
        'keep_button' => 'Manter convite',
        'cancel_button' => 'Cancelar convite',
        'pending_title' => 'Convites de equipe pendentes',
        'pending_description' => 'Aceite ou recuse as equipes para as quais você foi convidado.',
        'invited_by' => ':name convidou você para participar desta equipe.',
        'decline' => 'Recusar',
        'accept' => 'Aceitar',
    ],
    'leave' => [
        'tooltip' => 'Sair da equipe',
        'title' => 'Sair da equipe',
        'confirm_prefix' => 'Tem certeza de que deseja sair da equipe',
        'button' => 'Sair da equipe',
    ],
    'switcher' => [
        'select' => 'Selecionar equipe',
        'teams_label' => 'Equipes',
        'new_team' => 'Nova equipe',
    ],
];
