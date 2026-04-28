<?php

declare(strict_types=1);

namespace Arqel\Table;

/**
 * Fluent builder for Resource tables.
 *
 * Holds the declarative schema (columns + filters + actions +
 * config) and serialises it to the Inertia payload via
 * `toArray()`. The actual row data + pagination metadata are
 * injected by the resource controller (CORE-006) which combines
 * this schema with the result of `TableQueryBuilder` (TABLE-005).
 *
 * Action types (`actions`, `bulkActions`, `toolbarActions`) are
 * intentionally typed as `array<int, mixed>` until `arqel/actions`
 * ships. Apps can pass any structure today; the controller hands
 * the unmodified payload to the React side.
 *
 * Per-record column visibility (`canSee`) is honoured when the
 * controller serialises the row — the table builder itself does
 * not filter columns; that is per-column responsibility.
 */
final class Table
{
    public const string DIRECTION_ASC = 'asc';

    public const string DIRECTION_DESC = 'desc';

    /** @var array<int, mixed> */
    protected array $columns = [];

    /** @var array<int, mixed> */
    protected array $filters = [];

    /** @var array<int, mixed> */
    protected array $actions = [];

    /** @var array<int, mixed> */
    protected array $bulkActions = [];

    /** @var array<int, mixed> */
    protected array $toolbarActions = [];

    protected int $defaultPerPage = 25;

    /** @var array<int, int> */
    protected array $perPageOptions = [10, 25, 50, 100];

    protected ?string $defaultSortColumn = null;

    protected string $defaultSortDirection = self::DIRECTION_DESC;

    protected bool $searchable = true;

    protected bool $selectable = true;

    protected ?string $emptyStateHeading = null;

    protected ?string $emptyStateDescription = null;

    protected ?string $emptyStateIcon = null;

    protected bool $striped = false;

    protected bool $compact = false;

    /**
     * @param array<int, mixed> $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = array_values($columns);

        return $this;
    }

    /**
     * @param array<int, mixed> $filters
     */
    public function filters(array $filters): self
    {
        $this->filters = array_values($filters);

        return $this;
    }

    /**
     * @param array<int, mixed> $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = array_values($actions);

        return $this;
    }

    /**
     * @param array<int, mixed> $actions
     */
    public function bulkActions(array $actions): self
    {
        $this->bulkActions = array_values($actions);

        return $this;
    }

    /**
     * @param array<int, mixed> $actions
     */
    public function toolbarActions(array $actions): self
    {
        $this->toolbarActions = array_values($actions);

        return $this;
    }

    public function defaultSort(string $column, string $direction = self::DIRECTION_DESC): self
    {
        $this->defaultSortColumn = $column;
        $this->defaultSortDirection = $direction === self::DIRECTION_ASC
            ? self::DIRECTION_ASC
            : self::DIRECTION_DESC;

        return $this;
    }

    /**
     * @param array<int, int> $options
     */
    public function perPage(int $default, array $options = []): self
    {
        $this->defaultPerPage = $default;

        if ($options !== []) {
            $this->perPageOptions = array_values($options);
        }

        if (! in_array($default, $this->perPageOptions, true)) {
            $this->perPageOptions[] = $default;
            sort($this->perPageOptions);
        }

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function selectable(bool $selectable = true): self
    {
        $this->selectable = $selectable;

        return $this;
    }

    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): self
    {
        $this->emptyStateHeading = $heading;
        $this->emptyStateDescription = $description;
        $this->emptyStateIcon = $icon;

        return $this;
    }

    public function striped(bool $striped = true): self
    {
        $this->striped = $striped;

        return $this;
    }

    public function compact(bool $compact = true): self
    {
        $this->compact = $compact;

        return $this;
    }

    /** @return array<int, mixed> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return array<int, mixed> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @return array<int, mixed> */
    public function getActions(): array
    {
        return $this->actions;
    }

    /** @return array<int, mixed> */
    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /** @return array<int, mixed> */
    public function getToolbarActions(): array
    {
        return $this->toolbarActions;
    }

    public function getDefaultPerPage(): int
    {
        return $this->defaultPerPage;
    }

    /** @return array<int, int> */
    public function getPerPageOptions(): array
    {
        return $this->perPageOptions;
    }

    public function getDefaultSortColumn(): ?string
    {
        return $this->defaultSortColumn;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isSelectable(): bool
    {
        return $this->selectable;
    }

    public function isStriped(): bool
    {
        return $this->striped;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    /**
     * Serialise the table schema for the Inertia payload.
     *
     * Row data and pagination metadata are NOT included here — the
     * resource controller (CORE-006) merges this schema with the
     * result of `TableQueryBuilder` (TABLE-005) before shipping to
     * the React side.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'filters' => $this->filters,
            'actions' => $this->actions,
            'bulkActions' => $this->bulkActions,
            'toolbarActions' => $this->toolbarActions,
            'config' => [
                'defaultPerPage' => $this->defaultPerPage,
                'perPageOptions' => $this->perPageOptions,
                'defaultSort' => $this->defaultSortColumn !== null ? [
                    'column' => $this->defaultSortColumn,
                    'direction' => $this->defaultSortDirection,
                ] : null,
                'searchable' => $this->searchable,
                'selectable' => $this->selectable,
                'striped' => $this->striped,
                'compact' => $this->compact,
            ],
            'emptyState' => $this->emptyStateHeading !== null ? [
                'heading' => $this->emptyStateHeading,
                'description' => $this->emptyStateDescription,
                'icon' => $this->emptyStateIcon,
            ] : null,
        ];
    }
}
