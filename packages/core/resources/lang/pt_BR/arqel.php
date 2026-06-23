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
        'reset_title' => 'Definir nova senha',
        'reset_description' => 'Escolha uma nova senha para sua conta',
        'reset_hero_alt' => 'Ilustração de redefinição de senha',
        'reset_new_password' => 'Nova senha',
        'reset_submit' => 'Redefinir senha',
        'reset_submitting' => 'Salvando…',
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
    // Nomes acessíveis + placeholders visíveis dos inputs de campo do
    // framework (renderers React do @arqel-dev/fields). :label / :resource
    // são valores de runtime (já traduzidos) substituídos no cliente.
    'fields' => [
        'increment' => 'Incrementar',
        'decrement' => 'Decrementar',
        'file' => [
            'upload' => 'Envio de arquivo',
            'browse' => 'Procurar',
            'choose_another' => 'Escolher outro arquivo',
            'drop_hint' => 'Arraste um arquivo aqui ou clique para procurar',
        ],
        'image' => [
            'preview_alt' => 'Pré-visualização',
            'choose' => 'Escolher imagem',
            'replace' => 'Substituir imagem',
        ],
        'password' => [
            'show' => 'Mostrar senha',
            'hide' => 'Ocultar senha',
        ],
        'belongsto' => [
            'search' => 'Buscar :resource…',
        ],
        'multiselect' => [
            'remove' => 'Remover :label',
        ],
    ],
    'locale' => [
        'switch' => 'Idioma',
        'en' => 'English',
        'pt_BR' => 'Português (Brasil)',
    ],
    // Texto visível do <ConnectionStatusBanner> do @arqel-dev/realtime
    // (role=status, aria-live=polite). Exibido a cada desconexão/falha
    // do WebSocket.
    'realtime' => [
        'connecting' => 'Conectando...',
        'disconnected' => 'Conexão perdida. Reconectando...',
        'failed' => 'Falha na conexão. Atualize a página.',
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
    // aria-label + tooltip (title) do <ThemeToggle> de @arqel-dev/theme, que
    // alterna sistema → claro → escuro. Cada valor descreve o tema ATUAL e o
    // tema alcançado no próximo clique. Resolvido no cliente via
    // useArqelTranslations() com o literal em inglês como fallback.
    'theme' => [
        'toggle' => [
            'system' => 'Tema: sistema (clique para claro)',
            'light' => 'Tema: claro (clique para escuro)',
            'dark' => 'Tema: escuro (clique para sistema)',
        ],
    ],
    // Rótulo visível + focável do <SkipLink> de @arqel-dev/a11y. Texto padrão
    // do link de pular para o conteúdo; a prop `label` ainda o sobrescreve.
    // Resolvido no cliente via useArqelTranslations() com o literal em inglês
    // como fallback.
    'a11y' => [
        'skip_to_content' => 'Pular para o conteúdo principal',
    ],
    // Strings dos renderizadores de campo do @arqel-dev/ai (AiTextInput,
    // AiSelectInput, AiExtractInput, AiImageInput, AiTranslateInput):
    // rótulos padrão dos botões de ação, controles de ação visíveis,
    // banners de erro voltados ao usuário e as regiões sr-only/aria de
    // status. Resolvidos no cliente via useArqelTranslations() com o
    // literal em inglês como fallback.
    'ai' => [
        // Rótulos padrão dos botões de ação (quando o servidor omite buttonLabel).
        'generate' => 'Gerar com IA',
        'regenerate' => 'Gerar novamente',
        'classify' => 'Classificar com IA',
        'extract' => 'Extrair com IA',
        'analyze' => 'Analisar com IA',
        // Controles de ação visíveis.
        'apply' => 'Aplicar',
        'apply_field' => 'Aplicar :field',
        'apply_all' => 'Aplicar tudo',
        'translate_all_missing' => 'Traduzir tudo que falta',
        'translate_from' => 'Traduzir de :language',
        'missing_translation' => 'Tradução ausente',
        'source' => 'Fonte: :field',
        'select_placeholder' => 'Selecionar...',
        'image_file' => 'Arquivo de imagem',
        // Banners de erro voltados ao usuário (falha HTTP + erro de rede).
        'error_http' => 'Falha na geração (HTTP :status).',
        'error_network' => 'Falha na geração: erro de rede.',
        'classify_error_http' => 'Falha na classificação (HTTP :status).',
        'classify_error_none' => 'Não foi possível classificar.',
        'classify_error_network' => 'Falha na classificação: erro de rede.',
        'extract_error_http' => 'Falha na extração (HTTP :status).',
        'extract_error_invalid' => 'Falha na extração: corpo de resposta inválido.',
        'extract_error_network' => 'Falha na extração: erro de rede.',
        'analyze_error_http' => 'Falha na análise (HTTP :status).',
        'analyze_error_invalid' => 'Falha na análise: corpo de resposta inválido.',
        'analyze_error_network' => 'Falha na análise: erro de rede.',
        'translate_error_http' => 'Falha na tradução (HTTP :status).',
        'translate_error_invalid' => 'Falha na tradução: corpo de resposta inválido.',
        'translate_error_network' => 'Falha na tradução: erro de rede.',
        // Banners de validação no cliente + erros de configuração.
        'file_too_large' => 'Arquivo muito grande: :size (máx. :max).',
        'missing_translate_url' => 'URL de tradução ausente: forneça `translateUrl` ou ambos `resource` e `field`.',
        'missing_classify_url' => 'URL de classificação ausente: forneça `classifyUrl` ou ambos `resource` e `field`.',
        'classify_no_context_tooltip' => 'Nenhum campo de contexto configurado. Adicione `classifyFromFields` para habilitar a classificação por IA.',
        'missing_generate_url' => 'URL de geração ausente: forneça `generateUrl` ou ambos `resource` e `field`.',
        'missing_extract_url' => 'URL de extração ausente: forneça `extractUrl` ou ambos `resource` e `field`.',
        'missing_analyze_url' => 'URL de análise ausente: forneça `analyzeUrl` ou ambos `resource` e `field`.',
        'selected_preview_alt' => 'Pré-visualização selecionada',
        // Anúncios de status das regiões aria/sr-only.
        'status_generating' => 'Gerando',
        'status_classifying' => 'Classificando',
        'status_extracting' => 'Extraindo',
        'status_analyzing' => 'Analisando',
        'status_translating' => 'Traduzindo',
        // Selo de proveniência exibido após uma classificação (role="status").
        'suggestion_ai' => 'Sugerido por IA',
        'suggestion_fallback' => 'Usou alternativa',
    ],
    // Títulos de grupo visíveis na listbox do <CommandPalette> quando um comando
    // não tem categoria explícita, além do grupo sintético "Recentes". Resolvido
    // no cliente via useArqelTranslations() com o literal em inglês como fallback.
    'command_palette' => [
        'category_general' => 'Geral',
        'category_recent' => 'Recentes',
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
    // Rótulos visíveis das dicas de teclado no rodapé do <CommandPalette>. O
    // glifo (↑↓ / ↵ / esc) é renderizado pelo componente; apenas o verbo é
    // localizado. Resolvido no cliente via useArqelTranslations() com o literal
    // em inglês como fallback.
    'palette' => [
        'hint_navigate' => '↑↓ navegar',
        'hint_select' => '↵ selecionar',
        'hint_close' => 'esc fechar',
    ],
    // Nomes acessíveis + chrome visível da superfície de edição de conteúdo
    // rico fornecida por @arqel-dev/fields-advanced (Markdown / Repeater /
    // Wizard / Builder / RichText).
    'fields_advanced' => [
        'markdown_formatting' => 'Formatação Markdown',
        'markdown_bold' => 'Negrito',
        'markdown_italic' => 'Itálico',
        'markdown_heading' => 'Título',
        'markdown_code' => 'Código em linha',
        'markdown_link' => 'Link',
        'markdown_list' => 'Lista',
        'markdown_preview_open' => 'Abrir pré-visualização',
        'markdown_preview_label' => 'Pré-visualização',
        'markdown_enter_fullscreen' => 'Entrar em tela cheia',
        'markdown_exit_fullscreen' => 'Sair da tela cheia',
        'markdown_fullscreen_short' => 'Tela cheia',
        'markdown_exit_short' => 'Sair',
        'markdown_editor_mode' => 'Modo do editor',
        'markdown_preview' => 'Pré-visualização Markdown',
        'repeater_move_up' => 'Mover para cima',
        'repeater_move_down' => 'Mover para baixo',
        'repeater_add_item' => 'Adicionar item',
        'repeater_add_item_label' => '+ Adicionar item',
        'repeater_drag' => 'Arraste para reordenar o item :number',
        'repeater_expand' => 'Expandir item :number',
        'repeater_collapse' => 'Recolher item :number',
        'repeater_clone' => 'Clonar item :number',
        'repeater_remove' => 'Remover item :number',
        'wizard_back' => 'Voltar',
        'wizard_submit' => 'Enviar',
        'wizard_next' => 'Avançar',
        'wizard_step_label' => 'Etapa :number: :label',
        'wizard_progress' => 'Etapa :number de :total: :label',
        'wizard_no_fields' => 'Esta etapa não tem campos.',
        'wizard_empty' => 'Nenhuma etapa do assistente configurada.',
        'builder_close_picker' => 'Fechar seletor de blocos',
        'builder_add_block' => 'Adicionar bloco',
        'builder_add_block_label' => '+ Adicionar bloco',
        'builder_remove_block' => 'Remover bloco :number',
        'keyvalue_key' => 'Chave',
        'keyvalue_value' => 'Valor',
        'keyvalue_add_row' => 'Adicionar linha de :key / :value',
        'keyvalue_add_row_label' => '+ Adicionar linha',
        'keyvalue_remove_row' => 'Remover linha :number',
        'tags_remove' => 'Remover tag :tag',
        'code_enter_fullscreen' => 'Entrar em tela cheia',
        'code_exit_fullscreen' => 'Sair da tela cheia',
        'code_fullscreen_short' => 'Tela cheia',
        'code_exit_short' => 'Sair',
        'code_language_plaintext' => 'Texto simples',
        'richtext_toolbar' => 'Barra de formatação',
        'richtext_over_limit' => 'O conteúdo excede o comprimento máximo de :max caracteres.',
        'richtext_bold' => 'Negrito',
        'richtext_italic' => 'Itálico',
        'richtext_underline' => 'Sublinhado',
        'richtext_strike' => 'Tachado',
        'richtext_h1' => 'Título 1',
        'richtext_h2' => 'Título 2',
        'richtext_h3' => 'Título 3',
        'richtext_ul' => 'Lista com marcadores',
        'richtext_ol' => 'Lista numerada',
        'richtext_blockquote' => 'Citação',
        'richtext_link' => 'Link',
        'richtext_code' => 'Código em linha',
        'richtext_image' => 'Imagem',
        'richtext_image_disabled' => 'Upload de imagem não configurado',
        'richtext_placeholder' => 'Comece a escrever...',
    ],
    // Chrome visível + nomes acessíveis do <VersionTimeline> de
    // @arqel-dev/versioning. :id / :user / :relative / :summary alimentam o
    // nome acessível de cada item.
    'versioning' => [
        'initial' => 'Inicial',
        'compare' => 'Comparar',
        'restore' => 'Restaurar',
        'empty' => 'Nenhuma versão ainda.',
        'loading' => 'Carregando versões',
        'history' => 'Histórico de versões',
        'item_label' => 'Versão :id por :user, :relative: :summary',
        // Chrome visível + nomes acessíveis do <VersionDiff>.
        'modified' => 'Modificado',
        'no_changes' => 'Nenhuma alteração a exibir.',
        'field_comparison' => 'Comparação de campos',
        'no_previous_value' => 'sem valor anterior',
        'no_new_value' => 'sem novo valor',
    ],
    // Chrome de estado vazio visível do <StateTransition> de
    // @arqel-dev/workflow.
    'workflow' => [
        'no_state_assigned' => 'Nenhum estado atribuído.',
        'no_transitions' => 'Nenhuma transição disponível.',
    ],
    // Chrome de validação localizado da submissão de plugin do
    // @arqel-dev/marketplace (SubmitPluginRequest). `attributes` substitui os
    // nomes de campo em snake_case nos placeholders :attribute; `messages`
    // localiza falhas de regra independentemente das linhas de validação da
    // aplicação hospedeira.
    'marketplace' => [
        'attributes' => [
            'slug' => 'slug',
            'composer_package' => 'pacote Composer',
            'npm_package' => 'pacote npm',
            'github_url' => 'URL do GitHub',
            'type' => 'tipo',
            'name' => 'nome',
            'description' => 'descrição',
            'screenshots' => 'capturas de tela',
            'license' => 'licença',
        ],
        'messages' => [
            'slug_regex' => 'O slug pode conter apenas letras minúsculas, números e hífens.',
            'composer_package_regex' => 'O pacote Composer deve seguir o formato vendor/nome.',
            'type_in' => 'O tipo selecionado é inválido.',
        ],
    ],
];
