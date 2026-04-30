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
];
