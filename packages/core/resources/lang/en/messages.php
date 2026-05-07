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
];
