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

];
