<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
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
        $data = $this->pruneUnauthorizedFields($data, $instance, $this->resolveUser($request), null);

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
        $data = $this->pruneUnauthorizedFields($data, $instance, $this->resolveUser($request), $record);

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

        // Resolve records by the model's real primary key (#69). Hardcoding
        // `id` silently matched zero records for models with a custom key
        // (`protected $primaryKey = 'uuid'`). Mirrors ActionController::invokeBulk.
        $keyName = (new $modelClass)->getKeyName();

        $records = $modelClass::query()->whereIn($keyName, $recordIds)->get();

        $payload = $request->except(['_token', '_method', 'resource', 'action', 'record_ids']);
        $data = [];
        foreach ($payload as $key => $value) {
            $data[(string) $key] = $value;
        }

        // Forward the table's serialised columns to any bulk action that
        // accepts them (#67 A). Without this, column-driven actions like
        // ExportAction defaulted to an empty column list and produced a
        // BOM-only empty CSV. Duck-typed so core keeps no hard dep on
        // `arqel-dev/table` / `arqel-dev/export`.
        $table = $instance->table();
        if (method_exists($bulkAction, 'withColumns') && is_object($table) && method_exists($table, 'getColumns')) {
            $columns = $table->getColumns();
            if (is_array($columns)) {
                $bulkAction->withColumns($this->serializeColumns($columns));
            }
        }

        // Stock `delete` keeps its fast-path (no Action callback exists for
        // it — the semantics live here). Every other bulk action is run
        // through `execute()`, which safely no-ops when the action carries
        // neither a callback nor an overridden execute(). This is what lets
        // callback-less actions like ExportAction (which override execute()
        // directly) actually run instead of error-flashing (#48).
        $result = null;
        if ($action === 'delete' && ! (method_exists($bulkAction, 'hasCallback') && $bulkAction->hasCallback())) {
            $modelClass::query()->whereIn($keyName, $recordIds)->delete();
        } elseif (method_exists($bulkAction, 'execute')) {
            $result = $bulkAction->execute($records, $data);
        }

        $redirect = back()->with('success', __('arqel::messages.flash.bulk_completed'));

        // Surface a retrievable download URL when the action produced a
        // downloadable artifact (#67 B). The export package writes
        // `export-<id>.<ext>` into the dir its download controller globs;
        // we derive the id from the filename and flash the URL for the
        // `arqel.export.download` route when it is registered. Duck-typed
        // to avoid a core -> export dependency.
        //
        // NOTE: the full flash-notification + signed-URL pipeline remains
        // deferred to EXPORT-006/007/008; this is the minimal coherent
        // round-trip that makes the produced file reachable by the user.
        $downloadUrl = $this->resolveDownloadUrl($result);
        if ($downloadUrl !== null) {
            $redirect = $redirect->with('download_url', $downloadUrl);
        }

        return $redirect;
    }

    /**
     * Serialise a list of table column descriptors to the plain-array
     * shape exporters consume (`{type, name, label, ...}`). Column
     * objects expose `toArray()`; already-array descriptors pass through.
     *
     * @param array<mixed> $columns
     *
     * @return array<int, array<mixed>>
     */
    private function serializeColumns(array $columns): array
    {
        $serialized = [];

        foreach ($columns as $column) {
            if (is_array($column)) {
                $serialized[] = $column;

                continue;
            }

            if (is_object($column) && method_exists($column, 'toArray')) {
                $asArray = $column->toArray();
                if (is_array($asArray)) {
                    $serialized[] = $asArray;
                }
            }
        }

        return $serialized;
    }

    /**
     * Build a download URL from a bulk action's return payload when it
     * describes a produced file. Expects an array carrying a `filename`
     * shaped `export-<id>.<ext>`; returns null otherwise, or when the
     * `arqel.export.download` route is not registered (export package
     * absent or routing disabled).
     */
    private function resolveDownloadUrl(mixed $result): ?string
    {
        if (! is_array($result)) {
            return null;
        }

        $filename = $result['filename'] ?? null;
        if (! is_string($filename) || $filename === '') {
            return null;
        }

        if (preg_match('/^export-(.+)\.[^.]+$/', $filename, $matches) !== 1) {
            return null;
        }

        $exportId = $matches[1];

        // The download route constrains the id to `[a-f0-9-]+`; bail out
        // rather than emit a URL the route would reject.
        if (preg_match('/^[a-f0-9-]+$/', $exportId) !== 1) {
            return null;
        }

        if (! Route::has('arqel.export.download')) {
            return null;
        }

        return route('arqel.export.download', ['exportId' => $exportId]);
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

        // `null` means the rule extractor is genuinely absent
        // (`arqel-dev/form` not installed) — the documented permissive
        // fallback. An empty array means the extractor ran and the
        // Resource declared no rules. We must NOT treat an infra failure
        // (extractor present but broken) as "no rules": that path
        // fails closed inside extractRules() by throwing, never here.
        if ($rules === null) {
            $data = $request->except(['_token', '_method', 'resource', 'id']);

            $clean = [];
            foreach ($data as $key => $value) {
                $clean[(string) $key] = $value;
            }

            return $clean;
        }

        $validated = $request->validate($rules);
        $clean = [];
        foreach ($validated as $key => $value) {
            $clean[(string) $key] = $value;
        }

        return $clean;
    }

    /**
     * Resolve the active user as a typed `?Authenticatable`. The
     * request macro returns `mixed`; we narrow it with an instanceof
     * guard so the field-auth oracles get the precise type they
     * declare (and PHPStan stays happy without a cast).
     */
    private function resolveUser(Request $request): ?Authenticatable
    {
        $user = $request->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Strip from the validated payload any field whose field-level
     * authorization denies a write for this user/record (#102).
     *
     * Field `canSee()`/`canEdit()` predicates drove only the render
     * payload (readonly/hidden flags in `FieldSchemaSerializer`); they
     * were never consulted on the write path, so a user shown a
     * read-only or hidden field could still submit its value and have
     * it persisted (mass-assignment bypass).
     *
     * We reuse the same oracle the serializer uses to compute the
     * `readonly` flag — `canBeEditedBy($user, $record)`, which already
     * returns false when the field is not even visible
     * (`HasAuthorization` chains `canBeSeenBy` into `canBeEditedBy`).
     * So pruning on `canBeEditedBy` covers both `canEdit(false)` and
     * `canSee(false)`, keeping render and write in agreement.
     *
     * Fields without a predicate default to allowed and are untouched,
     * so the mainstream path (no field-auth declared) is unaffected.
     * The fields are duck-typed (`getName()` + `canBeEditedBy()`) so
     * `arqel-dev/core` keeps no hard dependency on `arqel-dev/fields`.
     *
     * On create `$record` is null (no row exists yet); on update it is
     * the loaded record, so per-record predicates see the right state.
     *
     * Layout-level visibility is enforced too (#115): a field whose only
     * guard is an enclosing hidden layout (`Section::canSee(...)`) is
     * absent from `effectiveFields($record)`. Such a field is dropped from
     * the payload by comparing against the record-agnostic field set, so a
     * value submitted for a layout the record cannot see is never
     * persisted — matching the render payload, which also omits it.
     *
     * Non-persisting modifiers are enforced too (#127): `dehydrated(false)`
     * (the documented don't-persist contract), `disabled()` and `readonly()`
     * are display-only/computed, so a value submitted for them is dropped
     * rather than mass-assigned. The oracles default to the persisting state,
     * leaving plain fields (no modifier) untouched.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function pruneUnauthorizedFields(
        array $data,
        Resource $resource,
        ?Authenticatable $user,
        ?Model $record,
    ): array {
        $visibleFields = $resource->effectiveFields($record);

        // Fields present in the schema but excluded for THIS record by an
        // enclosing hidden layout must have their submitted value dropped.
        $visibleNames = [];
        foreach ($visibleFields as $field) {
            if (is_object($field) && method_exists($field, 'getName')) {
                $name = $field->getName();
                if (is_string($name)) {
                    $visibleNames[$name] = true;
                }
            }
        }

        foreach ($resource->effectiveFields() as $field) {
            if (! is_object($field) || ! method_exists($field, 'getName')) {
                continue;
            }

            $name = $field->getName();
            if (! is_string($name) || ! array_key_exists($name, $data)) {
                continue;
            }

            if (! isset($visibleNames[$name])) {
                unset($data[$name]);
            }
        }

        // Per-field write auth (#102): drop values the user cannot edit.
        foreach ($visibleFields as $field) {
            if (! is_object($field) || ! method_exists($field, 'canBeEditedBy') || ! method_exists($field, 'getName')) {
                continue;
            }

            $name = $field->getName();
            if (! is_string($name) || ! array_key_exists($name, $data)) {
                continue;
            }

            if (! $field->canBeEditedBy($user, $record)) {
                unset($data[$name]);
            }
        }

        // Non-persisting modifiers (#127): drop values for fields the
        // framework renders as display-only or computed. `dehydrated(false)`
        // is the documented "don't persist" contract; `disabled()` and
        // `readonly()` are serialised display-only, so a value submitted
        // for one is a mass-assignment of a field the developer never
        // meant to accept. Each oracle defaults to the persisting state
        // (readonly=false, disabled=false, dehydrated=true), so a plain
        // field with no modifier is untouched and the mainstream path is
        // unaffected. Oracles are duck-typed to keep core decoupled from
        // `arqel-dev/fields`.
        foreach ($visibleFields as $field) {
            if (! is_object($field) || ! method_exists($field, 'getName')) {
                continue;
            }

            $name = $field->getName();
            if (! is_string($name) || ! array_key_exists($name, $data)) {
                continue;
            }

            if (method_exists($field, 'isDehydrated') && $field->isDehydrated($record) === false) {
                unset($data[$name]);

                continue;
            }

            if (method_exists($field, 'isDisabled') && $field->isDisabled($record) === true) {
                unset($data[$name]);

                continue;
            }

            if (method_exists($field, 'isReadonly') && $field->isReadonly() === true) {
                unset($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Extract validation rules from the Resource's Field schema.
     *
     * Returns `null` when the extractor is genuinely unavailable
     * (`arqel-dev/form` not installed) — the caller then applies the
     * documented permissive fallback. Returns an array (possibly empty)
     * when the extractor ran successfully.
     *
     * Fails CLOSED on infrastructure errors: if the extractor class
     * exists but cannot be instantiated, lacks an `extract()` method, or
     * throws, we raise a RuntimeException (HTTP 500) rather than silently
     * collapsing to "no rules" — which would accept unvalidated input
     * (mass assignment). The failure is logged for operators.
     *
     * @return array<string, array<int, mixed>>|null
     */
    private function extractRules(Resource $resource): ?array
    {
        $extractorClass = 'Arqel\\Form\\FieldRulesExtractor';

        if (! class_exists($extractorClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($extractorClass);
            $extractor = $reflection->newInstance();

            if (! method_exists($extractor, 'extract')) {
                throw new RuntimeException(
                    "[{$extractorClass}] exists but has no extract() method; refusing to skip validation.",
                );
            }

            $rules = $extractor->extract($resource->effectiveFields());
        } catch (ReflectionException|RuntimeException $e) {
            Log::error('Arqel: field-rule extraction failed; refusing to accept unvalidated input.', [
                'resource' => $resource::class,
                'extractor' => $extractorClass,
                'exception' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Field-rule extraction failed; refusing to skip validation.',
                0,
                $e,
            );
        }

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
