<?php

declare(strict_types=1);

namespace Arqel\Nav;

use Illuminate\Database\Eloquent\Model;

/**
 * Builds breadcrumb trails from an Inertia route name +
 * parameters.
 *
 * Convention: route names follow `arqel.{panel}.{resource}.{action}`.
 * Each segment becomes a breadcrumb; record-bound actions
 * (`edit`, `show`) interpolate the record's title via
 * `Resource::recordTitle($record)` when a model is passed in
 * params.
 *
 * Returns an array of `['label' => string, 'url' => ?string]`. The
 * last item has `url => null` (current page).
 */
final class BreadcrumbsBuilder
{
    public const string ACTION_INDEX = 'index';

    public const string ACTION_CREATE = 'create';

    public const string ACTION_EDIT = 'edit';

    public const string ACTION_SHOW = 'show';

    /** @var array<class-string> */
    protected array $resources = [];

    /**
     * @param array<int, class-string> $resources resources registered in the current panel
     */
    public function withResources(array $resources): self
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public function buildFor(string $routeName, array $params = []): array
    {
        $segments = $this->parseRouteName($routeName);
        if ($segments === null) {
            return [];
        }

        [$resourceSlug, $action] = $segments;

        $resourceClass = $this->findResourceBySlug($resourceSlug);
        $crumbs = [];

        $resolvedLabel = $resourceClass !== null && method_exists($resourceClass, 'getPluralLabel')
            ? $resourceClass::getPluralLabel()
            : null;

        $crumbs[] = [
            'label' => is_string($resolvedLabel) && $resolvedLabel !== '' ? $resolvedLabel : ucfirst($resourceSlug),
            'url' => '/'.$resourceSlug,
        ];

        if ($action === self::ACTION_INDEX) {
            $crumbs[count($crumbs) - 1]['url'] = null;

            return $crumbs;
        }

        if ($action === self::ACTION_CREATE) {
            $crumbs[] = ['label' => 'Create', 'url' => null];

            return $crumbs;
        }

        if (in_array($action, [self::ACTION_EDIT, self::ACTION_SHOW], true)) {
            $recordLabel = $this->resolveRecordTitle($resourceClass, $params);
            if ($recordLabel !== null) {
                $crumbs[] = [
                    'label' => $recordLabel,
                    'url' => $action === self::ACTION_EDIT
                        ? '/'.$resourceSlug.'/'.$this->resolveRecordKey($params)
                        : null,
                ];
            }

            if ($action === self::ACTION_EDIT) {
                $crumbs[] = ['label' => 'Edit', 'url' => null];
            }
        }

        return $crumbs;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseRouteName(string $routeName): ?array
    {
        $parts = explode('.', $routeName);
        $count = count($parts);

        if ($count < 2) {
            return null;
        }

        return [$parts[$count - 2], $parts[$count - 1]];
    }

    /**
     * @return class-string|null
     */
    private function findResourceBySlug(string $slug): ?string
    {
        foreach ($this->resources as $resourceClass) {
            if (! method_exists($resourceClass, 'getSlug')) {
                continue;
            }

            if ($resourceClass::getSlug() === $slug) {
                return $resourceClass;
            }
        }

        return null;
    }

    /**
     * @param class-string|null $resourceClass
     * @param array<string, mixed> $params
     */
    private function resolveRecordTitle(?string $resourceClass, array $params): ?string
    {
        $record = $params['record'] ?? $params['model'] ?? null;
        if (! $record instanceof Model) {
            return $this->resolveRecordKey($params);
        }

        if ($resourceClass !== null && method_exists($resourceClass, 'recordTitle')) {
            $title = (new $resourceClass)->recordTitle($record);
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        $key = $record->getKey();

        return is_scalar($key) ? (string) $key : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveRecordKey(array $params): ?string
    {
        foreach (['id', 'record', 'model'] as $key) {
            if (! isset($params[$key])) {
                continue;
            }

            $value = $params[$key];
            if ($value instanceof Model) {
                $key = $value->getKey();

                return is_scalar($key) ? (string) $key : null;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
