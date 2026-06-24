<?php

declare(strict_types=1);

return [
    // Command palette combobox input placeholder (also its accessible hint).
    'placeholder' => 'Type a command…',

    // Empty-results message shown when no commands match the query.
    'no_results' => 'No commands found',

    // Built-in command provider categories (grouping headers in the palette).
    'category' => [
        'navigation' => 'Navigation',
        'settings' => 'Settings',
    ],

    // NavigationCommandProvider — "Go to {plural label}" command label.
    // The :label value MUST keep the English literal so the accessible name
    // stays "Go to Users" etc. under the default locale.
    'go_to' => 'Go to :label',

    // ThemeCommandProvider — theme-switch command labels.
    'theme' => [
        'light' => 'Switch to light theme',
        'dark' => 'Switch to dark theme',
        'system' => 'Use system theme',
    ],
];
