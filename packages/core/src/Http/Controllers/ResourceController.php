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
