<?php

declare(strict_types=1);

return [
    'actions' => [
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'back' => 'Voltar',
        'view' => 'Visualizar',
        'restore' => 'Restaurar',
    ],
    'confirmation' => [
        'delete' => 'Tem certeza que deseja excluir?',
        'cannot_undo' => 'Esta ação não pode ser desfeita.',
    ],
    'flash' => [
        'created' => 'Registro criado.',
        'updated' => 'Registro atualizado.',
        'deleted' => 'Registro excluído.',
        'restored' => 'Registro restaurado.',
        'no_selection' => 'Nenhum registro selecionado.',
        'bulk_completed' => 'Ação em massa concluída.',
        'bulk_action_no_callback' => "A ação em massa ':action' não tem callback.",
    ],
    'errors' => [
        'forbidden' => 'Você não tem permissão para executar esta ação.',
        'not_found' => 'Registro não encontrado.',
    ],
    'export' => [
        'invalid_id' => 'ID de exportação inválido.',
        'not_found' => 'Exportação não encontrada.',
        'ambiguous' => 'Exportação ambígua.',
        'document_title' => 'Exportação',
        'boolean_yes' => 'Sim',
        'boolean_no' => 'Não',
    ],
    'locale' => [
        'invalid' => 'Idioma inválido.',
    ],
    'tenant' => [
        'feature_unavailable' => "O recurso ':feature' não está disponível no seu plano atual.",
        'no_current_tenant' => 'Nenhum tenant atual.',
        'not_resolved' => 'Nenhum tenant pôde ser resolvido para a requisição.',
    ],
    'action' => [
        'missing_selection' => 'Nenhuma seleção informada.',
        'failed' => 'A ação não pôde ser concluída.',
    ],
    'upload' => [
        'not_file_field' => 'O campo não é de upload de arquivo.',
        'missing_file' => 'Arquivo enviado ausente.',
        'persist_failed' => 'Não foi possível salvar o arquivo enviado.',
        'missing_path' => 'Caminho do arquivo ausente.',
        'invalid_path' => 'Caminho de arquivo inválido.',
        'path_outside_directory' => 'O caminho do arquivo está fora do diretório permitido.',
    ],
    'ai' => [
        'forbidden' => 'Acesso negado',
        'registry_unbound' => 'A IA está temporariamente indisponível.',
        'registry_contract_mismatch' => 'A IA está temporariamente indisponível.',
        'resource_not_registered' => 'Recurso [:resource] não registrado',
        'field_resolution_failed' => 'Não foi possível resolver os campos do recurso.',
        'field_not_found' => ':type [:field] não encontrado no recurso [:resource]',
        'provider_failed' => 'A requisição ao provedor de IA falhou',
        'image_source_required' => 'É necessário fornecer imageUrl ou imageBase64',
        'daily_limit_exceeded' => 'Limite diário de IA de $:limit excedido',
        'user_limit_exceeded' => 'Limite diário de IA de $:limit excedido para o usuário #:userId',
        'fields' => [
            'text' => [
                'button' => 'Gerar com IA',
            ],
            'extract' => [
                'button' => 'Extrair com IA',
            ],
            'image' => [
                'button' => 'Analisar com IA',
            ],
        ],
    ],
    'marketplace' => [
        'forbidden' => 'Acesso negado',
        'unauthenticated' => 'Não autenticado',
        'validation_failed' => 'Falha na validação',
        'license_required' => 'Licença obrigatória',
        'purchase_not_found' => 'Compra não encontrada',
        'review_not_found' => 'Avaliação não encontrada',
        'refund_failed' => 'Falha no reembolso pelo gateway',
        'payment_verification_failed' => 'Falha na verificação do pagamento',
        'purchase_already_refunded' => 'A compra já foi reembolsada.',
        'refund_only_completed' => 'Apenas compras concluídas podem ser reembolsadas.',
        'plugin_is_free' => 'O plugin é gratuito.',
        'payment_id_required' => 'paymentId é obrigatório.',
        'plugin_not_found' => 'Plugin [:slug] não encontrado',
        'category_not_found' => 'Categoria [:slug] não encontrada',
        'screenshots_count' => '{1}:count captura de tela fornecida.|[2,*]:count capturas de tela fornecidas.',
        'auto_check' => [
            'composer_package_invalid' => 'composer_package deve seguir o formato vendor/package (alfanumérico minúsculo + hifens).',
            'composer_package_ok' => 'O pacote Composer segue a convenção vendor/package.',
            'github_url_invalid' => 'github_url deve apontar para github.com.',
            'github_url_ok' => 'A URL do GitHub aponta para github.com.',
            'description_short' => 'A descrição é curta; considere 50+ caracteres para melhor descoberta.',
            'description_ok' => 'O tamanho da descrição é adequado.',
            'screenshots_missing' => 'Nenhuma captura de tela fornecida; pelo menos uma é recomendada.',
            'name_duplicate' => 'Outro plugin já usa este nome.',
            'name_unique' => 'O nome do plugin é único.',
        ],
        'security' => [
            'no_license' => 'O plugin não tem licença declarada.',
            'license_not_allowed' => 'A licença :license não está na lista de permissões recomendada.',
        ],
    ],
    'field_search' => [
        'not_searchable' => 'O campo não permite busca.',
        'disabled' => 'A busca está desabilitada para este campo.',
    ],
    'versioning' => [
        'not_versionable' => 'O modelo [:model] não usa o trait Versionable',
        'restore_failed' => 'Falha ao restaurar.',
        'registry_not_bound' => 'ResourceRegistry não está registrado',
        'forbidden' => 'Acesso negado',
        'version_not_found' => 'Versão não encontrada para o registro.',
        'registry_unavailable' => 'Registro de recursos indisponível.',
        'resource_not_found' => "Recurso ':resource' não encontrado.",
        'resource_no_model' => "O recurso ':resource' não tem um model vinculado.",
        'resource_not_registered' => 'Recurso [:resource] não está registrado',
        'resource_invalid' => 'Recurso [:resource] é inválido',
    ],
    'realtime' => [
        'collab' => [
            'invalid_state' => 'state deve ser uma string base64 não vazia',
            'invalid_base64' => 'state não é um base64 válido',
            'version_conflict' => 'conflito de versão',
        ],
    ],
    'workflow' => [
        'state_filter_label' => 'Estado',
    ],
    'filter' => [
        'trashed' => 'Excluídos',
    ],
];
