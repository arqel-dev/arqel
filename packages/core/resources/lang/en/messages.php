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
        'failed' => 'The action could not be completed.',
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
    'marketplace' => [
        'forbidden' => 'Forbidden',
        'unauthenticated' => 'Unauthenticated',
        'validation_failed' => 'Validation failed',
        'license_required' => 'License required',
        'purchase_not_found' => 'Purchase not found',
        'review_not_found' => 'Review not found',
        'refund_failed' => 'Refund failed at gateway',
        'payment_verification_failed' => 'Payment verification failed',
        'plugin_not_found' => 'Plugin [:slug] not found',
        'category_not_found' => 'Category [:slug] not found',
        'screenshots_count' => '{1}Provided :count screenshot.|[2,*]Provided :count screenshots.',
        'auto_check' => [
            'composer_package_invalid' => 'composer_package must match vendor/package (lowercase alnum + hyphens).',
            'composer_package_ok' => 'Composer package follows vendor/package convention.',
            'github_url_invalid' => 'github_url must point to github.com.',
            'github_url_ok' => 'GitHub URL points to github.com.',
            'description_short' => 'Description is short; consider 50+ characters for better discoverability.',
            'description_ok' => 'Description length is adequate.',
            'screenshots_missing' => 'No screenshots provided; at least one is recommended.',
            'name_duplicate' => 'Another plugin already uses this name.',
            'name_unique' => 'Plugin name is unique.',
        ],
    ],
    'field_search' => [
        'not_searchable' => 'Field is not searchable.',
        'disabled' => 'Field has search disabled.',
    ],
    'versioning' => [
        'not_versionable' => 'Model does not use the Versionable trait.',
        'restore_failed' => 'Restore failed.',
        'registry_not_bound' => 'ResourceRegistry not bound',
        'forbidden' => 'Forbidden',
        'version_not_found' => 'Version not found for record.',
        'registry_unavailable' => 'Resource registry unavailable.',
        'resource_not_found' => "Resource ':resource' not found.",
        'resource_no_model' => "Resource ':resource' has no model bound.",
        'resource_not_registered' => 'Resource [:resource] not registered',
        'resource_invalid' => 'Resource [:resource] is invalid',
    ],
    'realtime' => [
        'collab' => [
            'invalid_state' => 'state must be a non-empty base64 string',
            'invalid_base64' => 'state is not valid base64',
            'version_conflict' => 'version conflict',
        ],
    ],
    'workflow' => [
        'state_filter_label' => 'State',
    ],
];
