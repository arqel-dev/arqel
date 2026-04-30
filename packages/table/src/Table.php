<?php

declare(strict_types=1);

namespace Arqel\Table;

use Arqel\Table\Summaries\Summary;
use Closure;
use Illuminate\Support\Collection;

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

    public const string MOBILE_MODE_STACKED = 'stacked';

    public const string MOBILE_MODE_SCROLL = 'scroll';

    public const string PAGINATION_LENGTH_AWARE = 'lengthAware';

    public const string PAGINATION_SIMPLE = 'simple';

    public const string PAGINATION_CURSOR = 'cursor';

    public const string PAGINATION_INFINITE = 'infinite';

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

    protected string $mobileMode = self::MOBILE_MODE_STACKED;

    protected string $paginationType = self::PAGINATION_LENGTH_AWARE;

    protected ?string $groupBy = null;

    protected ?Closure $groupLabelResolver = null;

    /** @var array<int, Summary> */
    protected array $groupSummaries = [];

    protected ?string $reorderColumn = null;

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

    /**
     * Configure mobile rendering mode.
     *
     * Unknown values fall back to {@see self::MOBILE_MODE_STACKED}
     * defensively — this flag is read by the React layer to decide
     * between stacked-card layout and horizontal scroll. Throwing
     * here would crash Inertia render for a typo; the default is
     * the safe choice.
     */
    public function mobileMode(string $mode): self
    {
        $this->mobileMode = in_array($mode, [self::MOBILE_MODE_STACKED, self::MOBILE_MODE_SCROLL], true)
            ? $mode
            : self::MOBILE_MODE_STACKED;

        return $this;
    }

    public function getMobileMode(): string
    {
        return $this->mobileMode;
    }

    /**
     * Configure pagination type used by the resource controller.
     *
     * Unknown values fall back to {@see self::PAGINATION_LENGTH_AWARE}
     * defensively — the React layer reads this flag to decide between
     * length-aware paginator UI, simple prev/next buttons, cursor
     * navigation, or Inertia 3 `merge` infinite scroll. Throwing on
     * typo would crash the Inertia render; the safe default keeps
     * the table usable.
     */
    public function paginationType(string $type): self
    {
        $this->paginationType = in_array($type, [
            self::PAGINATION_LENGTH_AWARE,
            self::PAGINATION_SIMPLE,
            self::PAGINATION_CURSOR,
            self::PAGINATION_INFINITE,
        ], true)
            ? $type
            : self::PAGINATION_LENGTH_AWARE;

        return $this;
    }

    public function getPaginationType(): string
    {
        return $this->paginationType;
    }

    public function groupBy(string $field, ?Closure $labelResolver = null): self
    {
        $this->groupBy = $field;
        $this->groupLabelResolver = $labelResolver;

        return $this;
    }

    /**
     * @param array<int, mixed> $summaries
     */
    public function groupSummaries(array $summaries): self
    {
        $this->groupSummaries = array_values(array_filter(
            $summaries,
            static fn (mixed $summary): bool => $summary instanceof Summary,
        ));

        return $this;
    }

    public function getGroupBy(): ?string
    {
        return $this->groupBy;
    }

    public function getGroupLabel(mixed $record): string
    {
        if ($this->groupLabelResolver !== null) {
            return (string) ($this->groupLabelResolver)($record);
        }

        if ($this->groupBy === null) {
            return '';
        }

        $value = data_get($record, $this->groupBy);

        return $value === null ? '' : (string) $value;
    }

    /** @return array<int, Summary> */
    public function getGroupSummaries(): array
    {
        return $this->groupSummaries;
    }

    /**
     * Enable drag-drop row reordering.
     *
     * Calling without arguments uses the conventional `position`
     * column. Pass an explicit column name to use a different
     * integer column. Pass `null` to disable reordering after it
     * has been enabled.
     */
    public function reorderable(?string $columnName = 'position'): self
    {
        $this->reorderColumn = $columnName;

        return $this;
    }

    public function getReorderColumn(): ?string
    {
        return $this->reorderColumn;
    }

    public function isReorderable(): bool
    {
        return $this->reorderColumn !== null;
    }

    /**
     * Build groups against a Collection of records.
     *
     * Returns a list of `{label, key, records, summaries}`. When no
     * `groupBy` is configured, a single synthetic group `'All'` is
     * returned with all records and the configured summaries
     * computed against them. The render layer (React) consumes this
     * shape — the schema itself only carries the configuration via
     * `toArray()`.
     *
     * @param Collection<int, mixed> $records
     *
     * @return array<int, array{label: string, key: mixed, records: Collection<int, mixed>, summaries: array<int, array{type: string, field: ?string, label: ?string, value: mixed}>}>
     */
    public function buildGroups(Collection $records): array
    {
        if ($this->groupBy === null) {
            return [[
                'label' => 'All',
                'key' => null,
                'records' => $records,
                'summaries' => $this->computeSummaries($records),
            ]];
        }

        $field = $this->groupBy;

        $grouped = $records->groupBy(static fn (mixed $record): string => (string) data_get($record, $field));

        $groups = [];

        foreach ($grouped as $key => $groupRecords) {
            /** @var Collection<int, mixed> $groupRecords */
            $first = $groupRecords->first();
            $label = $this->groupLabelResolver !== null
                ? (string) ($this->groupLabelResolver)($first)
                : (string) $key;

            $groups[] = [
                'label' => $label,
                'key' => $key,
                'records' => $groupRecords,
                'summaries' => $this->computeSummaries($groupRecords),
            ];
        }

        return $groups;
    }

    /**
     * @param Collection<int, mixed> $records
     *
     * @return array<int, array{type: string, field: ?string, label: ?string, value: mixed}>
     */
    protected function computeSummaries(Collection $records): array
    {
        $out = [];

        foreach ($this->groupSummaries as $summary) {
            $out[] = array_merge(
                $summary->toArray(),
                ['value' => $summary->compute($records)],
            );
        }

        return $out;
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
                'mobileMode' => $this->mobileMode,
                'paginationType' => $this->paginationType,
            ],
            'emptyState' => $this->emptyStateHeading !== null ? [
                'heading' => $this->emptyStateHeading,
                'description' => $this->emptyStateDescription,
                'icon' => $this->emptyStateIcon,
            ] : null,
            'groupBy' => $this->groupBy,
            'summaries' => array_map(
                static fn (Summary $summary): array => $summary->toArray(),
                $this->groupSummaries,
            ),
            'reorderable' => $this->reorderColumn,
        ];
    }
}
