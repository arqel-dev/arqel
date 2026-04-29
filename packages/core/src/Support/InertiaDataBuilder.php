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
use ReflectionMethod;

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

        $fields = $resource->fields();

        return [
            'resource' => $this->resourceMeta($resource),
            'records' => $paginator->items(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'fields' => $this->serializer->serialize($fields, null, $this->currentUser()),
            // The plain fallback emits empty/derived versions of the
            // table-shaped keys so the React `<DataTable>` /
            // `<ResourceIndex>` components never have to guard for
            // `undefined` (`filters.length`, `columns.map`, etc.).
            'columns' => $this->deriveColumnsFromFields($fields),
            'filters' => [],
            'actions' => [
                'row' => [],
                'bulk' => [],
                'toolbar' => [],
            ],
            'search' => $request->input('search'),
            'sort' => [
                'column' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ],
        ];
    }

    /**
     * Derive a minimal `columns` payload from the Resource's fields
     * when no `Table::make()` is declared. Honours `visibility.table`
     * so fields hidden from tables stay hidden, and emits the same
     * shape the rich path would.
     *
     * @param array<int, mixed> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function deriveColumnsFromFields(array $fields): array
    {
        $columns = [];
        foreach ($fields as $field) {
            if (! is_object($field)) {
                continue;
            }

            // Honour visibility.table when the field exposes the
            // visibility oracle from `HasVisibility` — defaults to
            // visible when the trait is absent.
            if (method_exists($field, 'isVisibleIn') && $field->isVisibleIn('table') === false) {
                continue;
            }

            $name = method_exists($field, 'getName') ? $field->getName() : null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $label = method_exists($field, 'getLabel') ? $field->getLabel() : ucfirst($name);

            $columns[] = [
                'name' => $name,
                'type' => 'text',
                'label' => is_string($label) ? $label : ucfirst($name),
                'sortable' => false,
                'searchable' => false,
            ];
        }

        return $columns;
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

        $rowActions = $this->callTableArray($table, 'getActions');
        $user = $this->currentUser();

        $records = $paginator !== null
            ? array_map(fn ($r): array => $this->serializeRecord($r, $resource, $rowActions, $user), $paginator->items())
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
                'row' => $this->serializeMany($rowActions, $user),
                'bulk' => $this->serializeMany($this->callTableArray($table, 'getBulkActions'), $user),
                'toolbar' => $this->serializeMany($this->callTableArray($table, 'getToolbarActions'), $user),
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
        $user = $this->currentUser();
        [$fields, $form] = $this->resolveFormFields($resource);

        $payload = [
            'resource' => $this->resourceMeta($resource),
            'record' => null,
            'fields' => $this->serializer->serialize($fields, null, $user),
        ];

        if ($form !== null) {
            $payload['form'] = $form;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEditData(Resource $resource, Model $record, Request $request): array
    {
        $user = $this->currentUser();
        [$fields, $form] = $this->resolveFormFields($resource);

        $payload = [
            'resource' => $this->resourceMeta($resource),
            'record' => $record->toArray(),
            'recordTitle' => $resource->recordTitle($record),
            'recordSubtitle' => $resource->recordSubtitle($record),
            'fields' => $this->serializer->serialize($fields, $record, $user),
        ];

        if ($form !== null) {
            $payload['form'] = $form;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildShowData(Resource $resource, Model $record, Request $request): array
    {
        $user = $this->currentUser();
        [$fields, $form] = $this->resolveFormFields($resource);

        $payload = [
            'resource' => $this->resourceMeta($resource),
            'record' => $record->toArray(),
            'recordTitle' => $resource->recordTitle($record),
            'recordSubtitle' => $resource->recordSubtitle($record),
            'fields' => $this->serializer->serialize($fields, $record, $user),
        ];

        if ($form !== null) {
            $payload['form'] = $form;
        }

        return $payload;
    }

    /**
     * Returns the field list to serialise plus the optional layout
     * payload. When `Resource::form()` is declared, the schema (with
     * Section/Tabs/etc.) goes through `Form::toArray()` and the
     * field list is sourced from `Form::getFields()` (flatten).
     *
     * Duck-typed against `arqel/form` so `arqel/core` does not need
     * a hard dep.
     *
     * @return array{0: array<int, mixed>, 1: ?array<string, mixed>}
     */
    private function resolveFormFields(Resource $resource): array
    {
        $form = $resource->form();

        if (is_object($form)
            && method_exists($form, 'getFields')
            && method_exists($form, 'toArray')
        ) {
            $fields = $form->getFields();
            $payload = $form->toArray();

            $normalisedFields = is_array($fields) ? array_values($fields) : $resource->fields();
            $normalisedPayload = null;
            if (is_array($payload)) {
                $normalisedPayload = [];
                foreach ($payload as $key => $value) {
                    $normalisedPayload[(string) $key] = $value;
                }
            }

            return [$normalisedFields, $normalisedPayload];
        }

        return [$resource->fields(), null];
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
     * Serialise a list of toArray-able items. When `$user` is given
     * and the item's `toArray` is `Action::toArray($user, $record)`-
     * shaped (≥ 1 parameter), we pass it through so the action
     * payload can resolve user-aware fields (`disabled`, `url`).
     *
     * Duck-typed: items without `toArray` are skipped silently.
     *
     * @param array<int, mixed> $items
     *
     * @return list<array<string, mixed>>
     */
    private function serializeMany(array $items, ?Authenticatable $user = null): array
    {
        $serialized = [];
        foreach ($items as $item) {
            if (! is_object($item) || ! method_exists($item, 'toArray')) {
                continue;
            }

            $payload = $this->callToArray($item, $user);
            if (is_array($payload)) {
                $clean = [];
                foreach ($payload as $key => $value) {
                    $clean[(string) $key] = $value;
                }
                $serialized[] = $clean;
            }
        }

        return $serialized;
    }

    /**
     * Call `$item->toArray()` while respecting the actual signature.
     * Action-shaped objects accept `($user, $record)`; column/filter
     * objects take no args. ReflectionMethod inspection lets us pass
     * `$user` only when accepted.
     *
     * Caller already guarantees `method_exists($item, 'toArray')`,
     * so the dynamic dispatch is safe — PHPStan can't see that
     * across the call boundary, hence the explicit phpstan-ignore.
     */
    private function callToArray(object $item, ?Authenticatable $user): mixed
    {
        if ($user === null) {
            return $item->toArray(); // @phpstan-ignore method.notFound
        }

        try {
            $reflection = new ReflectionMethod($item, 'toArray');
            $params = $reflection->getNumberOfParameters();
        } catch (ReflectionException) {
            return $item->toArray(); // @phpstan-ignore method.notFound
        }

        if ($params >= 1) {
            return $item->toArray($user); // @phpstan-ignore method.notFound
        }

        return $item->toArray(); // @phpstan-ignore method.notFound
    }

    /**
     * Render a single record for index payloads. Adds Arqel-side
     * meta (recordTitle/recordSubtitle) on top of the model's
     * default `toArray`, plus the per-row visible-actions list
     * (resolved against `Action::isVisibleFor` + `canBeExecutedBy`).
     *
     * @param array<int, mixed> $rowActions Row actions declared on Resource::table
     *
     * @return array<string, mixed>
     */
    private function serializeRecord(mixed $record, Resource $resource, array $rowActions = [], ?Authenticatable $user = null): array
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
            'actions' => $this->resolveVisibleActionNames($rowActions, $record, $user),
        ];

        return $payload;
    }

    /**
     * Returns the names of row actions that are visible AND executable
     * for `$record` by `$user`. Duck-typed against `arqel/actions` so
     * `arqel/core` keeps no hard dep on it.
     *
     * @param array<int, mixed> $actions
     *
     * @return list<string>
     */
    private function resolveVisibleActionNames(array $actions, Model $record, ?Authenticatable $user): array
    {
        $names = [];
        foreach ($actions as $action) {
            if (! is_object($action)) {
                continue;
            }

            $name = method_exists($action, 'getName') ? $action->getName() : null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (method_exists($action, 'isVisibleFor') && $action->isVisibleFor($record) === false) {
                continue;
            }

            if (method_exists($action, 'canBeExecutedBy') && $action->canBeExecutedBy($user, $record) === false) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
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
