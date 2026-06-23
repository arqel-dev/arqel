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
        // Accessible names for the two date-range inputs (:label is the filter
        // label). Values must equal the original "<label> from" / "<label> to"
        // literals so the screen-reader accessible name stays stable.
        'date_from' => ':label from',
        'date_to' => ':label to',
    ],
    'bulk' => [
        // Pluralizable: the JS translator selects the form via Intl.PluralRules
        // ({one}/{other}) so "1 selected" never reads "1 selected(s)".
        'selected' => '{one} :count selected|{other} :count selected',
        'select_all' => 'Select all',
        // Accessible name for the header select-all checkbox (vs the short menu label above).
        'select_all_rows' => 'Select all rows',
        'clear' => 'Clear',
        // Accessible name for the bulk-actions <section> landmark.
        'label' => 'Bulk actions',
        // Per-row selection checkbox accessible name (:id is the record key).
        'select_row' => 'Select row :id',
    ],
];
