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
    ],
    'locale' => [
        'invalid' => 'Idioma inválido.',
    ],
    'tenant' => [
        'feature_unavailable' => "O recurso ':feature' não está disponível no seu plano atual.",
        'no_current_tenant' => 'Nenhum tenant atual.',
    ],
    'action' => [
        'missing_selection' => 'Nenhuma seleção informada.',
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
    ],
];
