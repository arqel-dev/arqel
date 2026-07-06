<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Resources\ResourceRegistry;
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
    public function __construct(private readonly ResourceRegistry $registry) {}

    public function index(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);

        $this->authorize('viewAny', $parentModel, $manager, null);

        $related = $parentModel->{$manager::$relationship}();
        // Reuse the existing table query pipeline against the relation query.
        $records = $related->get(); // MVP: full list; wire TableQueryBuilder pagination in Task 5.

        return response()->json([
            'records' => $records->toArray(),
            'table' => $manager->table()->toArray(),
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

        return back()->with('success', 'arqel::relations.created');
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
        $parentModel = $model::query()->findOrFail($parent);

        return [$resourceInstance, $manager, $parentModel];
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
}
