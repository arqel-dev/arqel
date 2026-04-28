<?php

declare(strict_types=1);

namespace Arqel\Nav;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Single sidebar entry. Resolves its destination from either a
 * literal `url`, a named route, or — when `resource()` is used —
 * the resource's index URL via `Resource::getIndexUrl()` (when
 * available).
 *
 * Visibility, badge, and badge colour can be set as Closures and
 * are resolved per-request when serialising for the Inertia
 * payload.
 */
final class NavigationItem
{
    protected string $label;

    protected ?string $icon = null;

    protected ?string $url = null;

    protected ?string $routeName = null;

    /** @var array<string, mixed> */
    protected array $routeParams = [];

    protected bool $openInNewTab = false;

    protected ?Closure $visible = null;

    protected int|string|Closure|null $badge = null;

    protected ?string $badgeColor = null;

    protected int $sort = 0;

    /** @var class-string|null */
    protected ?string $resourceClass = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * Build an item directly from a Resource class. Pulls label,
     * navigation icon, slug-derived URL, and sort order from
     * `Resource` static getters when present.
     *
     * @param class-string $resourceClass
     */
    public static function resource(string $resourceClass): self
    {
        $resolved = method_exists($resourceClass, 'getPluralLabel')
            ? $resourceClass::getPluralLabel()
            : class_basename($resourceClass);
        $label = is_string($resolved) ? $resolved : class_basename($resourceClass);

        $item = new self($label);
        $item->resourceClass = $resourceClass;

        if (method_exists($resourceClass, 'getNavigationIcon')) {
            $icon = $resourceClass::getNavigationIcon();
            if (is_string($icon) && $icon !== '') {
                $item->icon = $icon;
            }
        }

        if (method_exists($resourceClass, 'getNavigationSort')) {
            $sort = $resourceClass::getNavigationSort();
            if (is_int($sort)) {
                $item->sort = $sort;
            }
        }

        if (method_exists($resourceClass, 'getSlug')) {
            $slug = $resourceClass::getSlug();
            if (is_string($slug) && $slug !== '') {
                $item->url = '/'.ltrim($slug, '/');
            }
        }

        return $item;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;
        $this->routeName = null;
        $this->routeParams = [];

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function route(string $name, array $params = []): self
    {
        $this->routeName = $name;
        $this->routeParams = $params;
        $this->url = null;

        return $this;
    }

    public function openInNewTab(bool $new = true): self
    {
        $this->openInNewTab = $new;

        return $this;
    }

    public function visible(Closure $callback): self
    {
        $this->visible = $callback;

        return $this;
    }

    public function badge(int|string|Closure $value): self
    {
        $this->badge = $value;

        return $this;
    }

    public function badgeColor(string $color): self
    {
        $this->badgeColor = $color;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @return class-string|null
     */
    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function isVisibleFor(?Authenticatable $user = null): bool
    {
        if ($this->visible === null) {
            return true;
        }

        return (bool) ($this->visible)($user);
    }

    public function resolveBadge(): int|string|null
    {
        if ($this->badge === null) {
            return null;
        }

        if ($this->badge instanceof Closure) {
            $value = ($this->badge)();

            if ($value === null) {
                return null;
            }

            return is_int($value) || is_string($value) ? $value : null;
        }

        return $this->badge;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        return array_filter([
            'label' => $this->label,
            'icon' => $this->icon,
            'url' => $this->url,
            'routeName' => $this->routeName,
            'routeParams' => $this->routeParams === [] ? null : $this->routeParams,
            'openInNewTab' => $this->openInNewTab ?: null,
            'badge' => $this->resolveBadge(),
            'badgeColor' => $this->badgeColor,
            'sort' => $this->sort,
            'visible' => $this->isVisibleFor($user),
        ], static fn ($v): bool => $v !== null);
    }
}
