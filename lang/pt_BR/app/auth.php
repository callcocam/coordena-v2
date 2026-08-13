<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| app.auth.* — login, cadastro, recuperação de senha, verificação e 2FA
|--------------------------------------------------------------------------
*/

return [
    'login' => [
        'meta_title' => 'Entrar',
        'title' => 'Entre na sua conta',
        'description' => 'Informe seu e-mail e senha para acessar.',
        'submit' => 'Entrar',
        'forgot_password' => 'Esqueceu a senha?',
        'remember' => 'Manter conectado',
        'no_account' => 'Não tem uma conta?',
        'register_link' => 'Cadastre-se',
        'invitation_action' => 'Entrar',
    ],
    'register' => [
        'meta_title' => 'Criar conta',
        'title' => 'Crie sua conta',
        'description' => 'Preencha os dados abaixo para começar.',
        'submit' => 'Cadastrar',
        'has_account' => 'Já tem uma conta?',
        'login_link' => 'Entrar',
        'invitation_action' => 'Cadastrar',
    ],
    'forgot_password' => [
        'meta_title' => 'Esqueci a senha',
        'title' => 'Esqueceu a senha?',
        'description' => 'Informe seu e-mail para receber um link de redefinição.',
        'submit' => 'Enviar link de redefinição',
        'or_return' => 'Ou volte para',
        'login_link' => 'entrar',
    ],
    'reset_password' => [
        'meta_title' => 'Redefinir senha',
        'title' => 'Redefinir senha',
        'description' => 'Digite sua nova senha abaixo.',
        'submit' => 'Redefinir senha',
    ],
    'confirm_password' => [
        'meta_title' => 'Confirmar senha',
        'title' => 'Confirmar senha',
        'description' => 'Esta é uma área segura do aplicativo. Confirme sua senha antes de continuar.',
        'submit' => 'Confirmar senha',
        'passkey_label' => 'Confirmar com passkey',
        'passkey_loading' => 'Confirmando…',
        'passkey_separator' => 'Ou confirme com a senha',
    ],
    'verify_email' => [
        'meta_title' => 'Verificação de e-mail',
        'title' => 'Verifique seu e-mail',
        'description' => 'Confirme seu endereço de e-mail clicando no link que acabamos de enviar.',
        'sent' => 'Um novo link de verificação foi enviado para o e-mail informado no cadastro.',
        'resend' => 'Reenviar e-mail de verificação',
        'logout' => 'Sair',
    ],
    'two_factor' => [
        'meta_title' => 'Autenticação em dois fatores',
        'continue' => 'Continuar',
        'or_you_can' => 'ou você pode',
        'authentication' => [
            'title' => 'Código de autenticação',
            'description' => 'Digite o código de autenticação fornecido pelo seu aplicativo autenticador.',
            'toggle' => 'entrar usando um código de recuperação',
        ],
        'recovery' => [
            'title' => 'Código de recuperação',
            'description' => 'Confirme o acesso à sua conta digitando um dos seus códigos de recuperação de emergência.',
            'toggle' => 'entrar usando um código de autenticação',
            'placeholder' => 'Digite o código de recuperação',
        ],
    ],
    'invitation_alert' => [
        'message' => ':action para participar da equipe ":team".',
    ],
    'fields' => [
        'name' => 'Nome',
        'name_placeholder' => 'Nome completo',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'password' => 'Senha',
        'password_placeholder' => 'Senha',
        'password_confirmation' => 'Confirme a senha',
        'password_confirmation_placeholder' => 'Confirme a senha',
    ],
];
