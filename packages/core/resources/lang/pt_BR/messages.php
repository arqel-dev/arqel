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
    ],
];
