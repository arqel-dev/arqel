<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Global activity log URL
    |--------------------------------------------------------------------------
    |
    | The route path that exposes the global filterable activity log
    | (handled by GlobalActivityLogController). Future
    | ActivityLogResource integration in `arqel/core` reads this value
    | to build links from the panel navigation.
    */
    'global_log_url' => '/admin/audit/activity',

    /*
    |--------------------------------------------------------------------------
    | Navigation metadata
    |--------------------------------------------------------------------------
    |
    | Hints consumed by the deferred ActivityLogResource (cross-package
    | follow-up) when registering the nav entry inside an Arqel panel.
    */
    'navigation_label' => 'Activity Log',
    'navigation_group' => 'Settings',
    'navigation_icon' => 'activity',
];
