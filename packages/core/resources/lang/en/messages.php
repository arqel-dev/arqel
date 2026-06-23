<?php

declare(strict_types=1);

return [
    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'view' => 'View',
        'restore' => 'Restore',
    ],
    'confirmation' => [
        'delete' => 'Are you sure you want to delete this?',
        'cannot_undo' => 'This action cannot be undone.',
    ],
    'flash' => [
        'created' => 'Record created.',
        'updated' => 'Record updated.',
        'deleted' => 'Record deleted.',
        'restored' => 'Record restored.',
        'no_selection' => 'No records selected.',
        'bulk_completed' => 'Bulk action completed.',
        'bulk_action_no_callback' => "Bulk action ':action' has no callback.",
    ],
    'errors' => [
        'forbidden' => 'You are not authorized to perform this action.',
        'not_found' => 'Record not found.',
    ],
    'export' => [
        'invalid_id' => 'Invalid export id.',
        'not_found' => 'Export not found.',
        'ambiguous' => 'Export ambiguous.',
    ],
    'locale' => [
        'invalid' => 'Invalid locale.',
    ],
    'tenant' => [
        'feature_unavailable' => "The ':feature' feature is not available on your current plan.",
        'no_current_tenant' => 'No current tenant.',
    ],
    'action' => [
        'missing_selection' => 'Missing selection.',
    ],
    'upload' => [
        'not_file_field' => 'Field is not a file upload.',
        'missing_file' => 'Missing uploaded file.',
        'persist_failed' => 'Could not persist uploaded file.',
        'missing_path' => 'Missing file path.',
        'invalid_path' => 'Invalid file path.',
        'path_outside_directory' => 'File path is outside the allowed directory.',
    ],
    'ai' => [
        'forbidden' => 'Forbidden',
        'registry_unbound' => 'AI is temporarily unavailable.',
        'registry_contract_mismatch' => 'AI is temporarily unavailable.',
        'resource_not_registered' => 'Resource [:resource] not registered',
        'field_resolution_failed' => 'Could not resolve resource fields.',
        'field_not_found' => ':type [:field] not found on resource [:resource]',
        'provider_failed' => 'AI provider request failed',
        'image_source_required' => 'Either imageUrl or imageBase64 must be provided',
    ],
];
