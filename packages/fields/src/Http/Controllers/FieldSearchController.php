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
            abort(HttpResponse::HTTP_BAD_REQUEST, 'Field is not searchable.');
        }

        if (! $fieldInstance->isSearchable()) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Field has search disabled.');
        }

        $rawQuery = $request->input('q', '');
        $query = is_string($rawQuery) ? $rawQuery : '';

        $relatedResourceClass = $fieldInstance->getRelatedResource();
        /** @var class-string<Model> $relatedModelClass */
        $relatedModelClass = $relatedResourceClass::getModel();

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

    private function resolveResourceOrFail(string $slug): Resource
    {
        $class = $this->registry->findBySlug($slug);

        if ($class === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var resource $instance */
        $instance = app($class);

        return $instance;
    }

    private function resolveFieldOrFail(Resource $resource, string $name): Field
    {
        foreach ($resource->fields() as $field) {
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
