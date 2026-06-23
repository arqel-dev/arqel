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
    // Nomes acessíveis (aria-label / sr-only) da interface do framework.
    // Mantidos distintos dos rótulos visíveis curtos para que leitores de
    // tela anunciem um nome acessível completo e descritivo.
    'aria' => [
        'flash_dismiss' => 'Dispensar',
        'chart_loading' => 'Carregando gráfico',
        'stat_sparkline' => 'Minigráfico de tendência',
        'palette_title' => 'Paleta de comandos',
        'palette_results' => ':count comandos',
        'palette_list' => 'Comandos',
        'breadcrumb' => 'Trilha de navegação',
        'theme_toggle_light' => 'Mudar para o tema claro',
        'theme_toggle_dark' => 'Mudar para o tema escuro',
        'tenant_switch' => 'Trocar de tenant (atual: :tenant)',
    ],
];
