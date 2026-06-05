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
    | notifications, or rebroadcast through arqel-dev/realtime. Setting
    | `log_via` to `'silent'` disables the event but still performs the
    | underlying state mutation — useful for migrations / seeders.
    */
    'audit' => [
        'enabled' => env('ARQEL_WORKFLOW_AUDIT_ENABLED', true),
        'log_via' => env('ARQEL_WORKFLOW_AUDIT_LOG_VIA', 'event'),
    ],

    'broadcast_transitions' => env('ARQEL_WORKFLOW_BROADCAST', false),

    /*
    |--------------------------------------------------------------------------
    | Authorization (WF-006)
    |--------------------------------------------------------------------------
    |
    | `TransitionAuthorizer` decide se uma transition é permitida para um
    | par (user, record) via:
    |   1. método `authorizeFor($user, $record): bool` na transition class
    |   2. `Gate::allows("transition-{from}-to-{to}", $record)` quando a
    |      ability está registrada
    |   3. fallback controlado por `deny_when_undefined`.
    |
    | `deny_when_undefined => true` (default) **nega** transitions quando
    | nem `authorizeFor` nem Gate estão configurados — postura segura por
    | padrão. Apps em migração que dependiam do comportamento WF-003
    | (autorizar tudo na ausência de Gate) podem definir `false` para
    | reverter ao legacy mode.
    */
    'authorization' => [
        'deny_when_undefined' => env('ARQEL_WORKFLOW_DENY_WHEN_UNDEFINED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | State transition history (WF-007)
    |--------------------------------------------------------------------------
    |
    | Quando `history.enabled` está ativo, o `WorkflowServiceProvider`
    | registra o listener `PersistStateTransitionToHistory` para gravar
    | cada `StateTransitioned` em `arqel_state_transitions`. `limit`
    | controla quantas entradas o `StateTransitionField::resolveHistory()`
    | devolve por record.
    */
    'history' => [
        'enabled' => env('ARQEL_WORKFLOW_HISTORY_ENABLED', true),
        'limit' => env('ARQEL_WORKFLOW_HISTORY_LIMIT', 50),
    ],
];
