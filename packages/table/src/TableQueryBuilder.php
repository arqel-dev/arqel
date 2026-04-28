<?php

declare(strict_types=1);

namespace Arqel\Table;

use Arqel\Table\Columns\RelationshipColumn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Orchestrates the request → Eloquent query pipeline for a table.
 *
 * Order of operations: search → filters → sort → eager loading
 * → pagination. Sort columns are whitelisted against the
 * `sortable()` columns declared on the table to prevent SQL
 * injection via `?sort=...`. Searchable columns are likewise
 * whitelisted.
 *
 * `withQueryString()` on the paginator preserves filters/search
 * in pagination links so the user does not lose state on page
 * navigation.
 */
final class TableQueryBuilder
{
    /**
     * @param Builder<Model> $query
     */
    public function __construct(
        private readonly Table $table,
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function build(): LengthAwarePaginator
    {
        $this->applySearch();
        $this->applyFilters();
        $this->applySort();
        $this->applyEagerLoading();

        return $this->paginate();
    }

    private function applySearch(): void
    {
        if (! $this->table->isSearchable()) {
            return;
        }

        $search = $this->request->input('search');
        if (! is_string($search) || $search === '') {
            return;
        }

        $searchable = $this->getSearchableColumnNames();
        if ($searchable === []) {
            return;
        }

        $this->query->where(function (Builder $q) use ($searchable, $search): void {
            foreach ($searchable as $column) {
                $q->orWhere($column, 'LIKE', '%'.$search.'%');
            }
        });
    }

    private function applyFilters(): void
    {
        $filters = $this->request->input('filter', []);
        if (! is_array($filters)) {
            $filters = [];
        }

        foreach ($this->table->getFilters() as $filter) {
            if (! $filter instanceof Filter) {
                continue;
            }

            $value = $filters[$filter->getName()] ?? $filter->getDefault();
            $filter->applyToQuery($this->query, $value);
        }
    }

    private function applySort(): void
    {
        $sortColumn = $this->request->input('sort', $this->table->getDefaultSortColumn());
        $direction = $this->request->input('direction', $this->table->getDefaultSortDirection());

        if (! is_string($sortColumn) || $sortColumn === '') {
            return;
        }

        $direction = is_string($direction) && strtolower($direction) === Table::DIRECTION_ASC
            ? Table::DIRECTION_ASC
            : Table::DIRECTION_DESC;

        if (! in_array($sortColumn, $this->getSortableColumnNames(), true)) {
            return;
        }

        $this->query->orderBy($sortColumn, $direction);
    }

    private function applyEagerLoading(): void
    {
        $relations = $this->collectRelationsFromColumns();

        if ($relations !== []) {
            $this->query->with($relations);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    private function paginate(): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage();
        /** @var LengthAwarePaginator<int, Model> $paginator */
        $paginator = $this->query->paginate($perPage);

        return $paginator->withQueryString();
    }

    private function resolvePerPage(): int
    {
        $requested = $this->request->input('per_page');
        $allowed = $this->table->getPerPageOptions();
        $default = $this->table->getDefaultPerPage();

        if (is_numeric($requested)) {
            $value = (int) $requested;
            if (in_array($value, $allowed, true)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @return array<int, string>
     */
    private function getSearchableColumnNames(): array
    {
        $names = [];

        foreach ($this->table->getColumns() as $column) {
            if ($column instanceof Column && $column->isSearchable()) {
                $names[] = $column->getName();
            }
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function getSortableColumnNames(): array
    {
        $names = [];

        foreach ($this->table->getColumns() as $column) {
            if ($column instanceof Column && $column->isSortable()) {
                $names[] = $column->getName();
            }
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function collectRelationsFromColumns(): array
    {
        $relations = [];

        foreach ($this->table->getColumns() as $column) {
            if ($column instanceof RelationshipColumn) {
                $name = $column->getName();
                if ($name !== '' && ! in_array($name, $relations, true)) {
                    $relations[] = $name;
                }
            }
        }

        return $relations;
    }
}
