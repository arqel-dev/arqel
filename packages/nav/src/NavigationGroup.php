<?php

declare(strict_types=1);

namespace Arqel\Nav;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Collapsible sidebar section containing zero or more
 * `NavigationItem`s. Items are rendered in `sort` order; ties are
 * broken by insertion order via `array_values` after sorting.
 */
final class NavigationGroup
{
    protected string $label;

    protected ?string $icon = null;

    /** @var array<int, NavigationItem> */
    protected array $items = [];

    protected bool $collapsible = true;

    protected bool $collapsed = false;

    protected int $sort = 0;

    protected ?Closure $visible = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param array<int, mixed> $items
     */
    public function items(array $items): self
    {
        $this->items = array_values(array_filter(
            $items,
            static fn ($item): bool => $item instanceof NavigationItem,
        ));

        return $this;
    }

    public function addItem(NavigationItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function collapsible(bool $collapsible = true): self
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): self
    {
        $this->collapsed = $collapsed;
        if ($collapsed) {
            $this->collapsible = true;
        }

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function visible(Closure $callback): self
    {
        $this->visible = $callback;

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
     * @return array<int, NavigationItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isVisibleFor(?Authenticatable $user = null): bool
    {
        if ($this->visible === null) {
            return true;
        }

        return (bool) ($this->visible)($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        $items = [];
        foreach ($this->sortedItems() as $item) {
            if ($item->isVisibleFor($user)) {
                $items[] = $item->toArray($user);
            }
        }

        return [
            'kind' => 'group',
            'label' => $this->label,
            'icon' => $this->icon,
            'collapsible' => $this->collapsible,
            'collapsed' => $this->collapsed,
            'sort' => $this->sort,
            'items' => $items,
        ];
    }

    /**
     * @return array<int, NavigationItem>
     */
    private function sortedItems(): array
    {
        $items = $this->items;
        usort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

        return $items;
    }
}
