<?php

declare(strict_types=1);

namespace Arqel\Nav;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sidebar navigation builder.
 *
 * Holds a heterogeneous list of `NavigationItem`, `NavigationGroup`,
 * and dividers. `build($user)` returns the visibility-filtered,
 * sort-ordered tree as a payload-ready array. Auto-registration of
 * Resources is opt-out via `autoRegister(false)`.
 */
final class Navigation
{
    public const string ENTRY_DIVIDER = 'divider';

    /** @var array<int, NavigationItem|NavigationGroup|array{kind: string}> */
    protected array $entries = [];

    protected bool $autoRegisterResources = true;

    public function autoRegister(bool $auto = true): self
    {
        $this->autoRegisterResources = $auto;

        return $this;
    }

    public function shouldAutoRegister(): bool
    {
        return $this->autoRegisterResources;
    }

    public function item(NavigationItem $item): self
    {
        $this->entries[] = $item;

        return $this;
    }

    public function group(string $label, Closure $callback): self
    {
        $group = NavigationGroup::make($label);
        $callback($group);
        $this->entries[] = $group;

        return $this;
    }

    public function addGroup(NavigationGroup $group): self
    {
        $this->entries[] = $group;

        return $this;
    }

    public function divider(): self
    {
        $this->entries[] = ['kind' => self::ENTRY_DIVIDER];

        return $this;
    }

    public function clear(): self
    {
        $this->entries = [];

        return $this;
    }

    /**
     * @return array<int, NavigationItem|NavigationGroup|array{kind: string}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Auto-register Resources whose `getNavigationGroup()` is set
     * into the corresponding group, and standalone (no group) ones
     * as top-level items. Idempotent — items already present (by
     * `resourceClass`) are skipped.
     *
     * @param array<int, class-string> $resources
     */
    public function autoRegisterFromResources(array $resources): self
    {
        if (! $this->autoRegisterResources) {
            return $this;
        }

        $existingResources = $this->collectExistingResourceClasses();

        foreach ($resources as $resourceClass) {
            if (in_array($resourceClass, $existingResources, true)) {
                continue;
            }

            $item = NavigationItem::resource($resourceClass);
            $groupLabel = $this->resolveResourceGroup($resourceClass);

            if ($groupLabel !== null) {
                $this->ensureGroup($groupLabel)->addItem($item);
            } else {
                $this->entries[] = $item;
            }
        }

        return $this;
    }

    /**
     * Build the payload-ready tree, filtering by user visibility
     * and applying sort order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(?Authenticatable $user = null): array
    {
        $sorted = $this->sortEntries($this->entries);
        $payload = [];

        foreach ($sorted as $entry) {
            if (is_array($entry)) {
                $payload[] = $entry;

                continue;
            }

            if (! $entry->isVisibleFor($user)) {
                continue;
            }

            $payload[] = $entry instanceof NavigationGroup
                ? $entry->toArray($user)
                : ['kind' => 'item'] + $entry->toArray($user);
        }

        return $payload;
    }

    /**
     * @param class-string $resourceClass
     */
    private function resolveResourceGroup(string $resourceClass): ?string
    {
        if (! method_exists($resourceClass, 'getNavigationGroup')) {
            return null;
        }

        $group = $resourceClass::getNavigationGroup();

        return is_string($group) && $group !== '' ? $group : null;
    }

    private function ensureGroup(string $label): NavigationGroup
    {
        foreach ($this->entries as $entry) {
            if ($entry instanceof NavigationGroup && $entry->getLabel() === $label) {
                return $entry;
            }
        }

        $group = NavigationGroup::make($label);
        $this->entries[] = $group;

        return $group;
    }

    /**
     * @return array<int, class-string>
     */
    private function collectExistingResourceClasses(): array
    {
        $classes = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof NavigationItem && $entry->getResourceClass() !== null) {
                $classes[] = $entry->getResourceClass();

                continue;
            }
            if ($entry instanceof NavigationGroup) {
                foreach ($entry->getItems() as $item) {
                    if ($item->getResourceClass() !== null) {
                        $classes[] = $item->getResourceClass();
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * @param array<int, NavigationItem|NavigationGroup|array{kind: string}> $entries
     *
     * @return array<int, NavigationItem|NavigationGroup|array{kind: string}>
     */
    private function sortEntries(array $entries): array
    {
        usort(
            $entries,
            static function ($a, $b): int {
                $sortA = $a instanceof NavigationItem || $a instanceof NavigationGroup ? $a->getSort() : 0;
                $sortB = $b instanceof NavigationItem || $b instanceof NavigationGroup ? $b->getSort() : 0;

                return $sortA <=> $sortB;
            },
        );

        return $entries;
    }
}
