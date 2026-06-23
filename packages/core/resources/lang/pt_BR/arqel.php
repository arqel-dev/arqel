<?php

declare(strict_types=1);

return [
    'actions' => [
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
        'back' => 'Voltar',
        'view' => 'Visualizar',
        'restore' => 'Restaurar',
        'submit' => 'Enviar',
        'reset' => 'Limpar',
        'retry' => 'Tentar novamente',
        'menu' => 'Ações',
    ],
    'nav' => [
        'dashboard' => 'Painel',
        'settings' => 'Configurações',
        'logout' => 'Sair',
        'profile' => 'Perfil',
        'home' => 'Início',
    ],
    'auth' => [
        'login' => 'Entrar',
        'logout' => 'Sair',
        'register' => 'Cadastrar',
        'forgot_password' => 'Esqueceu sua senha?',
        'reset_password' => 'Redefinir senha',
        'remember_me' => 'Lembrar de mim',
        'email' => 'E-mail',
        'password' => 'Senha',
        'name' => 'Nome',
        'invalid_credentials' => 'As credenciais informadas não conferem com nossos registros.',
        // Textos das páginas de autenticação bundled (login/cadastro/recuperação
        // de senha/verificação de e-mail). Consumidos pelas páginas React de
        // @arqel-dev/auth via useArqelTranslations().
        'login_title' => 'Bem-vindo de volta',
        'login_description' => 'Entre na sua conta',
        'login_hero_alt' => 'Ilustração de login',
        'login_submit' => 'Entrar',
        'login_submitting' => 'Entrando…',
        'no_account' => 'Não tem uma conta?',
        'sign_up' => 'Criar conta',
        'sign_in' => 'Entrar',
        'register_title' => 'Criar uma conta',
        'register_description' => 'Cadastre-se para acessar o painel administrativo',
        'register_hero_alt' => 'Ilustração de cadastro',
        'register_submit' => 'Criar conta',
        'register_submitting' => 'Criando conta…',
        'confirm_password' => 'Confirmar senha',
        'have_account' => 'Já tem uma conta?',
        'forgot_title' => 'Recuperar senha',
        'forgot_description' => 'Enviaremos um link de redefinição para o seu e-mail',
        'forgot_hero_alt' => 'Ilustração de recuperação de senha',
        'forgot_submit' => 'Enviar link de redefinição',
        'forgot_submitting' => 'Enviando…',
        'back_to_login' => 'Voltar ao login',
        'verify_title' => 'Verifique seu e-mail',
        'verify_hero_alt' => 'Ilustração de verificação de e-mail',
        'verify_intro' => 'Enviamos um link de verificação para :email. Confira sua caixa de entrada.',
        'verify_intro_generic' => 'Enviamos um link de verificação para o seu e-mail. Confira sua caixa de entrada.',
        'verify_resent' => 'Um novo link de verificação foi enviado.',
        'verify_not_received' => 'Não recebeu? Clique abaixo para reenviar.',
        'verify_resend' => 'Reenviar link',
        'verify_resending' => 'Enviando…',
        'reset_link_sent' => 'Um link de redefinição foi enviado, caso o e-mail exista.',
    ],
    'messages' => [
        'unsaved_changes' => 'Você tem alterações não salvas.',
        'delete_confirm' => 'Tem certeza que deseja excluir?',
        'cannot_undo' => 'Esta ação não pode ser desfeita.',
        // Prompt de confirmação por digitação; :value é exibido como <code> inline.
        'type_to_confirm' => 'Digite :value para confirmar',
        'created' => 'Registro criado.',
        'updated' => 'Registro atualizado.',
        'deleted' => 'Registro excluído.',
        'restored' => 'Registro restaurado.',
    ],
    'errors' => [
        'unauthorized' => 'Você não tem autorização para executar esta ação.',
        'forbidden' => 'Acesso negado.',
        'not_found' => 'Registro não encontrado.',
        'server_error' => 'Ocorreu um erro inesperado.',
    ],
    'locale' => [
        'switch' => 'Idioma',
        'en' => 'English',
        'pt_BR' => 'Português (Brasil)',
    ],
    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Próximo',
        'showing' => 'Exibindo :from a :to de :total resultados',
    ],
    // Títulos H1 visíveis das páginas CRUD padrão. :label é o rótulo
    // (já traduzido) singular do recurso; 'fallback' é usado quando não há
    // rótulo nem título de registro disponível.
    'pages' => [
        'create' => 'Criar :label',
        'edit' => 'Editar :label',
        'record' => 'Registro',
        'fallback' => 'registro',
    ],
    'tenant' => [
        // Nome de fallback (visível + anunciado) para um tenant sem `name`.
        'unnamed' => 'Tenant :id',
    ],
    // Nomes acessíveis (aria-label / sr-only) da interface do framework.
    // Mantidos distintos dos rótulos visíveis curtos para que leitores de
    // tela anunciem um nome acessível completo e descritivo.
    'aria' => [
        'flash_dismiss' => 'Dispensar',
        'chart_loading' => 'Carregando gráfico',
        'stat_sparkline' => 'Minigráfico de tendência',
        'palette_title' => 'Paleta de comandos',
        // Região sr-only pluralizável: "1 comando" / "N comandos".
        'palette_results' => '{one} :count comando|{other} :count comandos',
        'palette_list' => 'Comandos',
        'breadcrumb' => 'Trilha de navegação',
        'theme_toggle_light' => 'Mudar para o tema claro',
        'theme_toggle_dark' => 'Mudar para o tema escuro',
        'tenant_switch' => 'Trocar de tenant (atual: :tenant)',
    ],
];
