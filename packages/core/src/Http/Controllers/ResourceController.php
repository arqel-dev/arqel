<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Generic Resource controller. Polymorphic over the slug supplied
 * in the route (`/{resource}`); each method:
 *
 *  1. Resolves the Resource class via the registry (404 if absent)
 *  2. Authorises through Laravel's Gate using the standard
 *     ability names `viewAny`/`create`/`view`/`update`/`delete`
 *  3. Materialises the Inertia payload via `InertiaDataBuilder`
 *  4. Calls the Resource's lifecycle orchestrators
 *     (`runCreate`/`runUpdate`/`runDelete`) for writes
 *
 * View names follow `arqel::{action}` and are wired up by the
 * React side (CORE-012). Validation in this iteration is
 * intentionally lightweight — full FormRequest generation lands in
 * FORM-007.
 */
final class ResourceController
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly InertiaDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request, string $resource): Response
    {
        $instance = $this->resolveOrFail($resource);

        $this->authorize('viewAny', $instance::getModel());

        return Inertia::render('arqel::index', $this->dataBuilder->buildIndexData($instance, $request));
    }

    public function create(Request $request, string $resource): Response
    {
        $instance = $this->resolveOrFail($resource);

        $this->authorize('create', $instance::getModel());

        return Inertia::render('arqel::create', $this->dataBuilder->buildCreateData($instance, $request));
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);

        $this->authorize('create', $instance::getModel());

        $data = $this->validated($request, $instance);

        $record = $instance->runCreate($data);

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $instance::getSlug(), 'id' => $record->getKey()])
            ->with('success', __('arqel::messages.flash.created'));
    }

    public function show(Request $request, string $resource, string $id): Response
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        $this->authorize('view', $record);

        return Inertia::render('arqel::show', $this->dataBuilder->buildShowData($instance, $record, $request));
    }

    public function edit(Request $request, string $resource, string $id): Response
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        $this->authorize('update', $record);

        return Inertia::render('arqel::edit', $this->dataBuilder->buildEditData($instance, $record, $request));
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        $this->authorize('update', $record);

        $data = $this->validated($request, $instance);

        $instance->runUpdate($record, $data);

        return redirect()
            ->route('arqel.resources.edit', ['resource' => $instance::getSlug(), 'id' => $record->getKey()])
            ->with('success', __('arqel::messages.flash.updated'));
    }

    public function destroy(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        $this->authorize('delete', $record);

        $instance->runDelete($record);

        return redirect()
            ->route('arqel.resources.index', ['resource' => $instance::getSlug()])
            ->with('success', __('arqel::messages.flash.deleted'));
    }

    /**
     * Dispatch a bulk action (BUG-VAL-010). Looks up the action by
     * name on the resource's table BulkAction list, authorises via
     * Gate (`delete` ability for the stock delete fallback), then
     * either invokes the user-defined callback or applies the stock
     * `delete` semantics.
     *
     * The frontend POSTs `{ record_ids: [...] }` from the table's
     * selection state. Empty selections short-circuit with a flash
     * error rather than touching the DB.
     */
    public function bulkAction(Request $request, string $resource, string $action): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $modelClass = $instance::getModel();

        $bulkAction = $this->findBulkAction($instance, $action);

        if ($bulkAction === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // Stock delete uses the model-level `delete` ability; user-defined
        // bulk actions get the same gate by default — finer-grained
        // authorisation lives on Action::canBeExecutedBy (ACTIONS-005).
        $this->authorize('delete', $modelClass);

        $recordIds = $request->input('record_ids', []);
        if (! is_array($recordIds) || $recordIds === []) {
            return back()->with('error', __('arqel::messages.flash.no_selection'));
        }

        $records = $modelClass::query()->whereIn('id', $recordIds)->get();

        $payload = $request->except(['_token', '_method', 'resource', 'action', 'record_ids']);
        $data = [];
        foreach ($payload as $key => $value) {
            $data[(string) $key] = $value;
        }

        if (method_exists($bulkAction, 'hasCallback') && $bulkAction->hasCallback()) {
            $bulkAction->execute($records, $data);
        } elseif ($action === 'delete') {
            $modelClass::query()->whereIn('id', $recordIds)->delete();
        } else {
            return back()->with('error', __('arqel::messages.flash.bulk_action_no_callback', ['action' => $action]));
        }

        return back()->with('success', __('arqel::messages.flash.bulk_completed'));
    }

    /**
     * Walk the resource's table BulkAction list duck-typed (so this
     * file keeps no hard dep on `arqel-dev/actions`). Returns null
     * when the table or action is not found.
     */
    private function findBulkAction(Resource $instance, string $action): ?object
    {
        $table = $instance->table();
        if (! is_object($table) || ! method_exists($table, 'getBulkActions')) {
            return null;
        }

        $bulkActions = $table->getBulkActions();
        if (! is_array($bulkActions)) {
            return null;
        }

        foreach ($bulkActions as $candidate) {
            if (! is_object($candidate) || ! method_exists($candidate, 'getName')) {
                continue;
            }

            if ($candidate->getName() === $action) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveOrFail(string $slug): Resource
    {
        $class = $this->registry->findBySlug($slug);

        if ($class === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var resource $instance */
        $instance = app($class);

        return $instance;
    }

    private function findOrFail(Resource $resource, string $id): Model
    {
        $modelClass = $resource::getModel();

        $record = $modelClass::query()->find($id);

        if (! $record instanceof Model) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return $record;
    }

    /**
     * Authorise via Laravel's Gate. Misses (no Policy registered
     * for the model) silently allow — Resource Policies are
     * user-owned, and forcing them in scaffold mode would break
     * "Hello World" usage.
     */
    private function authorize(string $ability, mixed $arguments): void
    {
        if (! Gate::has($ability)) {
            $modelClass = is_object($arguments) ? $arguments::class : (is_string($arguments) ? $arguments : null);
            if ($modelClass === null || ! Gate::getPolicyFor($modelClass)) {
                return;
            }
        }

        if (Gate::denies($ability, $arguments)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Run validation against the rules extracted from the
     * Resource's Field schema (FORM-007). When `arqel-dev/form` is not
     * installed (extractor class missing) we fall back to a
     * permissive pass that just strips route + CSRF params.
     *
     * Hand-rolled FormRequests still take precedence — when Laravel
     * type-hint resolution wires one through the route, this method
     * is bypassed automatically.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, Resource $resource): array
    {
        $rules = $this->extractRules($resource);

        if ($rules !== []) {
            $validated = $request->validate($rules);
            $clean = [];
            foreach ($validated as $key => $value) {
                $clean[(string) $key] = $value;
            }

            return $clean;
        }

        $data = $request->except(['_token', '_method', 'resource', 'id']);

        $clean = [];
        foreach ($data as $key => $value) {
            $clean[(string) $key] = $value;
        }

        return $clean;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function extractRules(Resource $resource): array
    {
        $extractorClass = 'Arqel\\Form\\FieldRulesExtractor';

        if (! class_exists($extractorClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($extractorClass);
            $extractor = $reflection->newInstance();
        } catch (ReflectionException) {
            return [];
        }

        if (! method_exists($extractor, 'extract')) {
            return [];
        }

        $rules = $extractor->extract($resource->fields());

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
}
