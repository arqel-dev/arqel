<?php

declare(strict_types=1);

return [
    'empty' => 'No records found.',
    'loading' => 'Loading…',
    'per_page' => 'Per page',
    'search' => [
        'label' => 'Search',
        'placeholder' => 'Search...',
        // Resource-aware placeholder, e.g. "Search posts…".
        'placeholder_for' => 'Search :resource…',
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
        // Compact range summary (visible + announced).
        'range' => ':from–:to of :total',
        // Accessible name for the pagination <nav> landmark.
        'label' => 'Pagination',
    ],
    'column' => [
        // Accessible name for the otherwise-empty row-actions column header.
        'actions' => 'Actions',
    ],
    'sort' => [
        'asc' => 'Ascending',
        'desc' => 'Descending',
    ],
    'filters' => [
        'apply' => 'Apply',
        'reset' => 'Reset',
        'all' => 'All',
        'yes' => 'Yes',
        'no' => 'No',
        'clear' => 'Clear filters (:count)',
        // Accessible name for the filters <fieldset> legend (sr-only).
        'legend' => 'Filters',
    ],
    'bulk' => [
        'selected' => ':count selected',
        'select_all' => 'Select all',
        'clear' => 'Clear',
        // Accessible name for the bulk-actions <section> landmark.
        'label' => 'Bulk actions',
        // Per-row selection checkbox accessible name (:id is the record key).
        'select_row' => 'Select row :id',
    ],
];
