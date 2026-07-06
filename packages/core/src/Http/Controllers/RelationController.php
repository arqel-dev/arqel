<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic controller for relation-scoped CRUD + attach/detach. Every
 * endpoint resolves the parent Resource + RelationManager, scopes the
 * query to the parent (anti-IDOR), and authorizes against the related
 * model's Policy (fail-open when neither a Gate rule nor a Policy is
 * registered — matches ResourceController::authorize()'s two-tier
 * semantics).
 */
final class RelationController
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly InertiaDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);

        $this->authorize('viewAny', $parentModel, $manager, null);

        $related = $parentModel->{$manager::$relationship}();
        // Reuse the existing table query pipeline against the relation query.
        $records = $related->get(); // MVP: full list; wire TableQueryBuilder pagination in Task 5.

        // Serialize each related record through the same column pipeline the
        // main resource index uses (computed/state columns + per-record
        // canSee redaction, #206/#182) — review finding I1. Without this, a
        // relation table showed blank ComputedColumn cells and leaked
        // canSee(fn => false)-gated values that the equivalent resource
        // index column would strip. Duck-typed `getColumns()` access mirrors
        // `InertiaDataBuilder::callTableArray($table, 'getColumns')`; a
        // table without columns (or without `getColumns()` at all) falls
        // back to the raw `toArray()` payload, so behavior is unchanged.
        $table = $manager->table();
        $columns = is_object($table) && method_exists($table, 'getColumns')
            ? $table->getColumns()
            : [];
        $columns = is_array($columns) ? array_values($columns) : [];

        $serializedRecords = $columns === []
            ? $records->toArray()
            : $records->map(fn (Model $record): array => $this->dataBuilder->applyColumnSerialization($record, $columns))->all();

        // NOT a raw $table->toArray(): its 'columns' are unserialized Column
        // objects, which JSON-encode to `{}` (no name/label) and crash the
        // React DataTable (col.name undefined -> "Columns require an id
        // when using an accessorFn"). serializeTableSchema() reuses the
        // same callTableArray/serializeMany pipeline
        // RelationManager::toArray() and the resource index use, so all
        // three surfaces stay consistent.
        $tableSchema = is_object($table) && method_exists($table, 'toArray')
            ? $this->dataBuilder->serializeTableSchema($table, $request->user())
            : [];

        return response()->json([
            'records' => $serializedRecords,
            'table' => $tableSchema,
            'abilities' => $manager->abilities($parentModel, $request->user()),
        ]);
    }

    public function create(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $this->authorize('create', $parentModel, $manager, null);

        return response()->json([
            'fields' => app(\Arqel\Core\Support\FieldSchemaSerializer::class)->serialize($manager->fields(), null, $request->user()),
        ]);
    }

    public function store(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $this->authorize('create', $parentModel, $manager, null);

        $validated = $request->validate($this->rulesFromFields($manager));

        $parentModel->{$manager::$relationship}()->create($validated);

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $resource, 'id' => $parent])
            ->with('success', (string) __('arqel::relations.created'));
    }

    public function edit(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('update', $parentModel, $manager, $record);

        return response()->json([
            'fields' => app(\Arqel\Core\Support\FieldSchemaSerializer::class)->serialize($manager->fields(), $record, $request->user()),
            'record' => $record->toArray(),
        ]);
    }

    public function update(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('update', $parentModel, $manager, $record);

        $validated = $request->validate($this->rulesFromFields($manager));
        $record->update($validated);

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $resource, 'id' => $parent])
            ->with('success', (string) __('arqel::relations.updated'));
    }

    public function destroy(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('delete', $parentModel, $manager, $record);

        $record->delete();

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $resource, 'id' => $parent])
            ->with('success', (string) __('arqel::relations.deleted'));
    }

    public function attach(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        abort_unless($manager->supportsAttach($parentModel), Response::HTTP_METHOD_NOT_ALLOWED);
        // Class-level authz is intentional here: the related record does not
        // yet exist in this relation (that's the whole point of attach), so
        // there is no specific record to pass to the Gate.
        $this->authorizeAttach('attach', 'create', $parentModel, $manager);

        $validated = $request->validate(['related' => ['required']]);

        // Mass-assignment guard: only pivot columns explicitly allowlisted
        // via RelationManager::pivotFields() may be set by the client.
        // Anything else is silently dropped rather than reaching attach().
        $allowed = $manager->pivotFields();
        $rawPivot = $request->input('pivot');
        $pivot = $allowed === []
            ? []
            : array_intersect_key(
                is_array($rawPivot) ? $rawPivot : [],
                array_flip($allowed),
            );

        $parentModel->{$manager::$relationship}()->attach($validated['related'], $pivot);

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $resource, 'id' => $parent])
            ->with('success', (string) __('arqel::relations.attached'));
    }

    public function detach(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        abort_unless($manager->supportsAttach($parentModel), Response::HTTP_METHOD_NOT_ALLOWED);
        // Record-level authz: unlike attach(), the related record IS already
        // known here, so it is resolved (scoped to the parent — anti-IDOR)
        // and passed to the Gate so record-level policies are enforceable.
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorizeAttach('detach', 'delete', $parentModel, $manager, $record);

        $parentModel->{$manager::$relationship}()->detach($record->getKey());

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $resource, 'id' => $parent])
            ->with('success', (string) __('arqel::relations.detached'));
    }

    /**
     * Extract validation rules from the manager's fields via the SAME
     * string-referenced FieldRulesExtractor that ResourceController::extractRules()
     * uses — keeps `core` free of a hard dependency on arqel-dev/form.
     *
     * Unlike `ResourceController::extractRules()`, this returns `[]` (not
     * an error) when the extractor is unavailable: a relation form is
     * optional, so an absent extractor behaves like a manager that
     * declares no fields rather than a hard failure.
     *
     * @return array<string, mixed>
     */
    private function rulesFromFields(RelationManager $manager): array
    {
        $extractorClass = 'Arqel\\Form\\FieldRulesExtractor';
        if (! class_exists($extractorClass)) {
            return [];
        }

        $extractor = (new ReflectionClass($extractorClass))->newInstance();
        if (! method_exists($extractor, 'extract')) {
            return [];
        }

        $rules = $extractor->extract($manager->fields());
        if (! is_array($rules)) {
            return [];
        }

        $clean = [];
        foreach ($rules as $name => $set) {
            if (is_string($name) && is_array($set)) {
                $clean[$name] = $set;
            }
        }

        return $clean;
    }

    /**
     * Resolve [resourceInstance, manager, parentModel] or abort 404.
     *
     * @return array{0: object, 1: RelationManager, 2: Model}
     */
    private function resolve(string $resource, string|int $parent, string $relation): array
    {
        $resourceClass = $this->registry->findBySlug($resource);
        abort_if($resourceClass === null, Response::HTTP_NOT_FOUND);

        $resourceInstance = new $resourceClass;
        $managers = $resourceInstance->getRelations();
        abort_unless(isset($managers[$relation]), Response::HTTP_NOT_FOUND);

        $manager = $managers[$relation];
        $model = $resourceClass::$model;
        $parentModel = $model::query()->find($parent);
        abort_if($parentModel === null, Response::HTTP_NOT_FOUND);

        return [$resourceInstance, $manager, $parentModel];
    }

    /**
     * Resolve the related record scoped to the parent's relation query
     * (anti-IDOR): a related id belonging to a DIFFERENT parent is absent
     * from this scoped query. We use `find()` + an explicit `abort_if(...,
     * Response::HTTP_NOT_FOUND)` rather than `findOrFail()`: `findOrFail()`
     * throws `Illuminate\Database\Eloquent\ModelNotFoundException`, which
     * only becomes an HTTP 404 once it passes through Laravel's exception
     * handler — under direct controller invocation (this package's test
     * convention, no HTTP kernel involved) it would surface as a raw,
     * untyped exception instead of a testable 404.
     */
    private function findRelated(Model $parentModel, RelationManager $manager, string|int $related): Model
    {
        $record = $parentModel->{$manager::$relationship}()->find($related);
        abort_if($record === null, Response::HTTP_NOT_FOUND);

        return $record;
    }

    /**
     * Gate an ability against the related model's Policy. Fail-open only
     * when neither a Gate rule nor a Policy is registered for the related
     * model — matches ResourceController::authorize()'s two-tier semantics
     * (a Gate::define()'d rule with no Policy class must still be enforced).
     */
    private function authorize(string $ability, Model $parentModel, RelationManager $manager, ?Model $related): void
    {
        $relatedClass = $parentModel->{$manager::$relationship}()->getRelated()::class;

        if (! Gate::has($ability) && Gate::getPolicyFor($relatedClass) === null) {
            return; // fail-open: no gate rule AND no policy registered
        }

        $target = $related ?? $relatedClass;
        abort_if(Gate::denies($ability, $target), Response::HTTP_FORBIDDEN);
    }

    /**
     * Attach/detach authz: try the bespoke ability first, fall back to the
     * CRUD ability, fail-open when neither a Gate rule nor a Policy exists
     * for either ability — matches `authorize()`'s two-tier semantics.
     *
     * `$record`, when given, is passed to the Gate INSTEAD of the related
     * class so record-level Policies/rules are actually reachable (detach
     * always has a record; attach never does — see call site comments).
     */
    private function authorizeAttach(string $ability, string $fallback, Model $parentModel, RelationManager $manager, ?Model $record = null): void
    {
        $relatedClass = $parentModel->{$manager::$relationship}()->getRelated()::class;

        $hasRule = Gate::has($ability) || Gate::has($fallback) || Gate::getPolicyFor($relatedClass) !== null;
        if (! $hasRule) {
            return; // fail-open: no gate rule AND no policy registered
        }

        $target = $record ?? $relatedClass;
        $allowed = Gate::allows($ability, $target) || Gate::allows($fallback, $target);
        abort_unless($allowed, Response::HTTP_FORBIDDEN);
    }
}
