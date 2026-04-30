<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default state colors
    |--------------------------------------------------------------------------
    |
    | Fallback colors applied by `WorkflowDefinition::getStateMetadata()`
    | when a state is registered without an explicit `color` entry. Keys
    | match Arqel's semantic token names (primary/secondary/success/etc.).
    */
    'default_color' => 'secondary',

    /*
    |--------------------------------------------------------------------------
    | Default state icon
    |--------------------------------------------------------------------------
    |
    | Lucide icon name applied as a fallback when a state's metadata does
    | not declare an `icon`. The React-side WorkflowVisualizer (WF-005,
    | future) reads this when rendering state badges.
    */
    'default_icon' => 'circle',

    /*
    |--------------------------------------------------------------------------
    | Audit + broadcast (WF-004)
    |--------------------------------------------------------------------------
    |
    | When `audit.enabled` is true and `audit.log_via` is `'event'`, the
    | `HasWorkflow::transitionTo()` helper fires a `StateTransitioned`
    | event so user-land listeners can persist audit log entries, send
    | notifications, or rebroadcast through arqel/realtime. Setting
    | `log_via` to `'silent'` disables the event but still performs the
    | underlying state mutation — useful for migrations / seeders.
    */
    'audit' => [
        'enabled' => env('ARQEL_WORKFLOW_AUDIT_ENABLED', true),
        'log_via' => env('ARQEL_WORKFLOW_AUDIT_LOG_VIA', 'event'),
    ],

    'broadcast_transitions' => env('ARQEL_WORKFLOW_BROADCAST', false),
];
