<?php

declare(strict_types=1);

namespace Arqel\Fields\Http\Controllers;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Field;
use Arqel\Fields\Types\BelongsToField;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * GET endpoint for `BelongsToField` async search.
 *
 * Route shape:
 *   GET {panel}/{resource}/fields/{field}/search?q=...
 *
 * Returns a JSON list of `{value, label}` pairs (max 20). The
 * field's `searchColumns()` decide which DB columns are LIKE-matched
 * — when none are configured we fall back to `name`/`title`/`label`.
 *
 * The label callback (`optionLabel(Closure)`) on the field is
 * honoured per record; without it we use the related Resource's
 * `recordTitle`.
 *
 * Authorization (#128): beyond the panel middleware (authentication),
 * the endpoint gates the *related* model against its `viewAny` Policy
 * before querying it, so a user without read access to the related
 * Resource cannot enumerate its records' labels/PII. When no Policy is
 * registered the gate silently allows (scaffold mode), mirroring
 * `ResourceController::authorize()`.
 */
final class FieldSearchController
{
    public const int DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    public function __invoke(Request $request, string $resource, string $field): JsonResponse
    {
        $instance = $this->resolveResourceOrFail($resource);
        $fieldInstance = $this->resolveFieldOrFail($instance, $field);

        if (! $fieldInstance instanceof BelongsToField) {
            abort(
                HttpResponse::HTTP_BAD_REQUEST,
                $this->message('arqel::messages.field_search.not_searchable', 'Field is not searchable.'),
            );
        }

        if (! $fieldInstance->isSearchable()) {
            abort(
                HttpResponse::HTTP_FORBIDDEN,
                $this->message('arqel::messages.field_search.disabled', 'Field has search disabled.'),
            );
        }

        $rawQuery = $request->input('q', '');
        $query = is_string($rawQuery) ? $rawQuery : '';

        $relatedResourceClass = $fieldInstance->getRelatedResource();
        /** @var class-string<Model> $relatedModelClass */
        $relatedModelClass = $relatedResourceClass::getModel();

        $this->authorizeViewAny($relatedModelClass, $request);

        /** @var Builder<Model> $builder */
        $builder = $relatedModelClass::query();

        $relationQuery = $fieldInstance->getRelationQuery();
        if ($relationQuery instanceof Closure) {
            $result = $relationQuery($builder);
            if ($result instanceof Builder) {
                $builder = $result;
            }
        }

        if ($query !== '') {
            $columns = $fieldInstance->getSearchColumns();
            if ($columns === []) {
                $columns = $this->guessSearchColumns($relatedModelClass);
            }

            $builder->where(function (Builder $q) use ($columns, $query): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%'.$query.'%');
                }
            });
        }

        $records = $builder->limit(self::DEFAULT_LIMIT)->get();

        $relatedResource = app($relatedResourceClass);
        $optionLabel = $fieldInstance->getOptionLabel();

        $payload = [];
        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            $key = $record->getKey();
            $label = $optionLabel instanceof Closure
                ? $optionLabel($record)
                : (method_exists($relatedResource, 'recordTitle')
                    ? $relatedResource->recordTitle($record)
                    : (is_scalar($key) ? (string) $key : ''));

            $payload[] = [
                'value' => is_scalar($key) ? $key : null,
                'label' => is_scalar($label) ? (string) $label : '',
            ];
        }

        return response()->json($payload);
    }

    /**
     * Gate the related model against its `viewAny` Policy before we
     * read it. Mirrors `ResourceController::authorize()`: when no
     * Policy (and no matching ability gate) exists, allow silently so
     * scaffold usage keeps working.
     *
     * @param class-string<Model> $modelClass
     */
    private function authorizeViewAny(string $modelClass, Request $request): void
    {
        $ability = 'viewAny';

        if (! Gate::has($ability) && ! Gate::getPolicyFor($modelClass)) {
            return;
        }

        if (Gate::forUser($request->user())->denies($ability, $modelClass)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Localize an abort message lazily so the request locale applies. Falls
     * back to the English literal when no translator is bound or the key is
     * untranslated, keeping the user-facing error text stable.
     */
    private function message(string $key, string $fallback): string
    {
        if (! app()->bound('translator')) {
            return $fallback;
        }

        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
    }

    private function resolveResourceOrFail(string $slug): Resource
    {
        $class = $this->registry->findBySlug($slug);

        if ($class === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var \Arqel\Core\Resources\Resource $instance */
        $instance = app($class);

        return $instance;
    }

    private function resolveFieldOrFail(Resource $resource, string $name): Field
    {
        foreach ($resource->effectiveFields() as $field) {
            if ($field instanceof Field && $field->getName() === $name) {
                return $field;
            }
        }

        abort(HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * @param class-string<Model> $modelClass
     *
     * @return array<int, string>
     */
    private function guessSearchColumns(string $modelClass): array
    {
        $candidates = ['name', 'title', 'label'];
        $instance = new $modelClass;
        $available = $instance->getFillable();

        if ($available === []) {
            return $candidates;
        }

        $matches = array_values(array_intersect($candidates, $available));

        return $matches !== [] ? $matches : $candidates;
    }
}
