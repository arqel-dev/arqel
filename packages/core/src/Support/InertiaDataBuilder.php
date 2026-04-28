<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
    /**
     * @return array<string, mixed>
     */
    public function buildIndexData(Resource $resource, Request $request): array
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
            'fields' => $this->serializeFields($resource->fields()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateData(Resource $resource, Request $request): array
    {
        return [
            'resource' => $this->resourceMeta($resource),
            'record' => null,
            'fields' => $this->serializeFields($resource->fields()),
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
            'fields' => $this->serializeFields($resource->fields()),
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
            'fields' => $this->serializeFields($resource->fields()),
        ];
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
