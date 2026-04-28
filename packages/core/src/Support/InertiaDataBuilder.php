<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

use Arqel\Core\Resources\Resource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;
use ReflectionException;

/**
 * Assembles the Inertia payloads for the index/create/edit/show
 * pages of a Resource. The shape mirrors `06-api-react.md` §3 so
 * the React renderer can consume it without further reshaping.
 *
 * Field/Action serialisation is intentionally lightweight here:
 * each Field is serialised by calling `toArray()` if available,
 * otherwise we emit `{name, type}` as a fallback. The richer
 * serialiser lives in `arqel/fields`'s `FieldSchemaSerializer`
 * (CORE-010), which this builder defers to once it lands.
 */
final class InertiaDataBuilder
{
    public function __construct(
        private readonly FieldSchemaSerializer $serializer = new FieldSchemaSerializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildIndexData(Resource $resource, Request $request): array
    {
        $table = $resource->table();

        if (is_object($table) && $this->isTableObject($table)) {
            return $this->buildTableIndexData($resource, $table, $request);
        }

        return $this->buildPlainIndexData($resource, $request);
    }

    /**
     * Plain (no Table) fallback — paginate the model and emit the
     * minimum payload the React side needs to render a list.
     *
     * @return array<string, mixed>
     */
    private function buildPlainIndexData(Resource $resource, Request $request): array
    {
        $query = $this->resolveIndexQuery($resource);

        $perPageInput = $request->input('per_page', 25);
        $pageInput = $request->input('page', 1);
        $perPage = max(1, is_numeric($perPageInput) ? (int) $perPageInput : 25);
        $page = max(1, is_numeric($pageInput) ? (int) $pageInput : 1);

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return [
            'resource' => $this->resourceMeta($resource),
            'records' => $paginator->items(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'fields' => $this->serializer->serialize($resource->fields(), null, $this->currentUser()),
        ];
    }

    /**
     * Build the rich Inertia payload when the Resource declares a
     * Table. Delegates pagination to `TableQueryBuilder` (provided
     * by `arqel/table`) and serialises columns/filters/actions.
     *
     * Both `arqel/table` and `arqel/actions` are duck-typed — this
     * file lives in `arqel/core` and cannot import them as hard
     * deps.
     *
     * @return array<string, mixed>
     */
    private function buildTableIndexData(Resource $resource, object $table, Request $request): array
    {
        $query = $this->resolveIndexQuery($resource);
        $paginator = $this->runTableQueryBuilder($table, $query, $request);

        $records = $paginator !== null
            ? array_map(fn ($r): array => $this->serializeRecord($r, $resource), $paginator->items())
            : [];

        return [
            'resource' => $this->resourceMeta($resource),
            'records' => $records,
            'pagination' => $paginator !== null ? [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ] : null,
            'columns' => $this->serializeMany($this->callTableArray($table, 'getColumns')),
            'filters' => $this->serializeMany($this->callTableArray($table, 'getFilters')),
            'actions' => [
                'row' => $this->serializeMany($this->callTableArray($table, 'getActions')),
                'bulk' => $this->serializeMany($this->callTableArray($table, 'getBulkActions')),
                'toolbar' => $this->serializeMany($this->callTableArray($table, 'getToolbarActions')),
            ],
            'search' => $request->input('search'),
            'sort' => [
                'column' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function callTableArray(object $table, string $method): array
    {
        if (! method_exists($table, $method)) {
            return [];
        }

        $result = $table->{$method}();

        return is_array($result) ? array_values($result) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateData(Resource $resource, Request $request): array
    {
        return [
            'resource' => $this->resourceMeta($resource),
            'record' => null,
            'fields' => $this->serializer->serialize($resource->fields(), null, $this->currentUser()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEditData(Resource $resource, Model $record, Request $request): array
    {
        return [
            'resource' => $this->resourceMeta($resource),
            'record' => $record->toArray(),
            'recordTitle' => $resource->recordTitle($record),
            'recordSubtitle' => $resource->recordSubtitle($record),
            'fields' => $this->serializer->serialize($resource->fields(), $record, $this->currentUser()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildShowData(Resource $resource, Model $record, Request $request): array
    {
        return [
            'resource' => $this->resourceMeta($resource),
            'record' => $record->toArray(),
            'recordTitle' => $resource->recordTitle($record),
            'recordSubtitle' => $resource->recordSubtitle($record),
            'fields' => $this->serializer->serialize($resource->fields(), $record, $this->currentUser()),
        ];
    }

    private function currentUser(): ?Authenticatable
    {
        $user = Auth::user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceMeta(Resource $resource): array
    {
        $class = $resource::class;

        return [
            'class' => $class,
            'slug' => $class::getSlug(),
            'label' => $class::getLabel(),
            'pluralLabel' => $class::getPluralLabel(),
            'navigationIcon' => $class::getNavigationIcon(),
            'navigationGroup' => $class::getNavigationGroup(),
        ];
    }

    /**
     * @return Builder<Model>
     */
    private function resolveIndexQuery(Resource $resource): Builder
    {
        $custom = $resource->indexQuery();

        if ($custom instanceof Builder) {
            /** @var Builder<Model> $custom */
            return $custom;
        }

        $modelClass = $resource::getModel();

        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        return $query;
    }

    /**
     * Detect whether `$candidate` looks like an `Arqel\Table\Table`
     * (or a structurally-compatible builder). Duck-typed because
     * `arqel/core` does not depend on `arqel/table`.
     */
    private function isTableObject(object $candidate): bool
    {
        return method_exists($candidate, 'getColumns')
            && method_exists($candidate, 'getFilters');
    }

    /**
     * Run `Arqel\Table\TableQueryBuilder` against the table + query
     * + request when the class is available. Returns the paginator
     * or null when the class isn't installed (the controller then
     * falls back to a raw paginate).
     *
     * @param Builder<Model> $query
     *
     * @return LengthAwarePaginator<int, Model>|null
     */
    private function runTableQueryBuilder(object $table, Builder $query, Request $request): ?LengthAwarePaginator
    {
        $builderClass = 'Arqel\\Table\\TableQueryBuilder';

        if (! class_exists($builderClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($builderClass);
            $instance = $reflection->newInstance($table, $query, $request);
        } catch (ReflectionException) {
            return null;
        }

        if (! method_exists($instance, 'build')) {
            return null;
        }

        $result = $instance->build();

        return $result instanceof LengthAwarePaginator ? $result : null;
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return list<array<string, mixed>>
     */
    private function serializeMany(array $items): array
    {
        $serialized = [];
        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $payload = $item->toArray();
                if (is_array($payload)) {
                    /** @var array<string, mixed> $payload */
                    $serialized[] = $payload;
                }
            }
        }

        return $serialized;
    }

    /**
     * Render a single record for index payloads. Adds Arqel-side
     * meta (recordTitle/recordSubtitle) on top of the model's
     * default `toArray`.
     *
     * @return array<string, mixed>
     */
    private function serializeRecord(mixed $record, Resource $resource): array
    {
        if (! $record instanceof Model) {
            if (! is_array($record)) {
                return [];
            }

            $clean = [];
            foreach ($record as $key => $value) {
                $clean[(string) $key] = $value;
            }

            return $clean;
        }

        $payload = [];
        foreach ($record->toArray() as $key => $value) {
            $payload[(string) $key] = $value;
        }
        $payload['arqel'] = [
            'title' => $resource->recordTitle($record),
            'subtitle' => $resource->recordSubtitle($record),
        ];

        return $payload;
    }

    /**
     * @param array<int, mixed> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function serializeFields(array $fields): array
    {
        $serialized = [];
        foreach ($fields as $field) {
            if (is_object($field) && method_exists($field, 'toArray')) {
                $payload = $field->toArray();
                if (is_array($payload)) {
                    /** @var array<string, mixed> $payload */
                    $serialized[] = $payload;
                }

                continue;
            }

            if (is_object($field) && method_exists($field, 'getName') && method_exists($field, 'getType')) {
                $name = $field->getName();
                $type = $field->getType();
                $serialized[] = [
                    'name' => is_string($name) ? $name : '',
                    'type' => is_string($type) ? $type : '',
                ];
            }
        }

        return $serialized;
    }
}
