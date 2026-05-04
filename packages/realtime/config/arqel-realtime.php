<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default broadcasting connection
    |--------------------------------------------------------------------------
    |
    | Driver alias used by Arqel Realtime when dispatching broadcast events.
    | Set to `null` to fall back to the framework's `broadcasting.default`.
    | When using Laravel Reverb (recommended), keep this `null` and configure
    | `BROADCAST_CONNECTION=reverb` in your `.env`.
    |
    */
    'connection' => env('ARQEL_REALTIME_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Channel prefix
    |--------------------------------------------------------------------------
    |
    | Prefix applied to the private channels used by ResourceUpdated and
    | future presence channels. The default `arqel` keeps Arqel's traffic
    | namespaced from the host application's own broadcasting channels.
    |
    */
    'channel_prefix' => 'arqel',

    /*
    |--------------------------------------------------------------------------
    | Auto-dispatch hooks
    |--------------------------------------------------------------------------
    |
    | Toggles for automatic event emission. Consumers opt in per Resource by
    | applying `Arqel\Realtime\Concerns\BroadcastsResourceUpdates`. These
    | flags act as a global kill switch (e.g., for tests) so events stop
    | firing without un-applying the trait.
    |
    */
    'auto_dispatch' => [
        'resource_updated' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence channels (RT-004)
    |--------------------------------------------------------------------------
    |
    | Configures the presence channel exposed by `routes/channels.php`. When
    | `enabled` is `false`, `PresenceChannelResolver::forResource()` raises a
    | `RealtimeException` so callers fail fast instead of silently building a
    | dead channel name. `channel_pattern` accepts the placeholders
    | `{resource}` (Resource slug) and `{recordId}` (primary key).
    |
    */
    'presence' => [
        'enabled' => env('ARQEL_REALTIME_PRESENCE_ENABLED', true),
        'channel_pattern' => 'arqel.presence.{resource}.{recordId}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow integration (RT-cross)
    |--------------------------------------------------------------------------
    |
    | Quando `arqel-dev/workflow` está instalado, o `RealtimeServiceProvider`
    | registra automaticamente o listener `BroadcastStateTransitionListener`
    | para o evento `Arqel\Workflow\Events\StateTransitioned`. Cada transição
    | dispara um `ResourceUpdated` broadcast, levando a UI a refrescar via
    | os canais `arqel.{slug}` / `arqel.{slug}.{id}`. Defina como `false`
    | para desativar globalmente (e.g. para isolar broadcasts em jobs/queue
    | workers).
    |
    */
    'workflow' => [
        'broadcast_state_transitions' => env('ARQEL_REALTIME_WORKFLOW_BROADCAST', true),
    ],

];
