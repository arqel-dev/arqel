<?php

declare(strict_types=1);

return [
    'empty' => 'No records found.',
    'per_page' => 'Per page',
    'search' => [
        'label' => 'Search',
        'placeholder' => 'Search...',
    ],
    'pagination' => [
        // Short button labels (visible text).
        'previous' => 'Previous',
        'next' => 'Next',
        // Descriptive accessible names (aria-label) — kept distinct from the
        // short labels so screen-reader users hear the full action.
        'previous_page' => 'Previous page',
        'next_page' => 'Next page',
        'showing' => 'Showing :from to :to of :total results',
    ],
    'sort' => [
        'asc' => 'Ascending',
        'desc' => 'Descending',
    ],
    'filters' => [
        'apply' => 'Apply',
        'reset' => 'Reset',
        'all' => 'All',
        'clear' => 'Clear filters (:count)',
    ],
    'bulk' => [
        'selected' => ':count selected',
        'select_all' => 'Select all',
        'clear' => 'Clear',
    ],
];
