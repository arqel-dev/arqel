<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Mini-table widget rendered inside a dashboard.
 *
 * Intentionally avoids importing `arqel-dev/table` so the dep graph
 * stays minimal (`arqel-dev/widgets` → `arqel-dev/core` only). Columns are
 * duck-typed: anything exposing `toArray()` gets serialised, others
 * are dropped silently.
 *
 * The query Closure must return an Eloquent\Builder (or a builder-
 * shaped object responding to `limit()->get()->toArray()`). Errors
 * raised by the Closure (e.g. a missing PDO driver in test envs)
 * are caught and surfaced as `loadError` on the payload so the
 * React side can render an error state instead of crashing the
 * entire dashboard.
 */
final class TableWidget extends Widget
{
    protected string $type = 'table';

    protected string $component = 'TableWidget';

    /** @var (Closure(): Builder<Model>)|null */
    protected ?Closure $query = null;

    protected int $limit = 10;

    /** @var array<int, mixed> */
    protected array $columns = [];

    protected string|Closure|null $seeAllUrl = null;

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * @param Closure(): Builder<Model> $query
     */
    public function query(Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * @param array<int, mixed> $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function seeAllUrl(string|Closure|null $url): static
    {
        $this->seeAllUrl = $url;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return array<int, mixed>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function data(): array
    {
        $payload = [
            'columns' => $this->serialiseColumns(),
            'records' => [],
            'limit' => $this->limit,
            'seeAllUrl' => $this->resolveSeeAllUrl(),
        ];

        if ($this->query === null) {
            return $payload;
        }

        try {
            $builder = ($this->query)();
            $records = $builder->limit($this->limit)->get()->toArray();
            $payload['records'] = $records;
        } catch (Throwable $e) {
            $payload['records'] = [];
            $payload['loadError'] = $e->getMessage();
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serialiseColumns(): array
    {
        $serialised = [];

        foreach ($this->columns as $column) {
            if (is_object($column) && method_exists($column, 'toArray')) {
                /** @var array<string, mixed> $asArray */
                $asArray = $column->toArray();
                $serialised[] = $asArray;
            }
        }

        return $serialised;
    }

    private function resolveSeeAllUrl(): ?string
    {
        $value = $this->seeAllUrl instanceof Closure
            ? ($this->seeAllUrl)()
            : $this->seeAllUrl;

        return is_string($value) ? $value : null;
    }
}
