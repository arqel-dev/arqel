<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Holds the declarative dashboard schema: a list of widgets with
 * shared layout config (column count, polling default, etc.).
 *
 * `Resource::widgets()` (or panel-level `widgets([])`) returns a
 * `Dashboard` instance; the controller serialises it for Inertia.
 */
final class Dashboard
{
    /** @var list<Widget> */
    private array $widgets = [];

    private int $columns = 12;

    private ?string $heading = null;

    private ?string $description = null;

    private ?Closure $canSee = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param list<Widget> $widgets
     */
    public function widgets(array $widgets): self
    {
        $valid = [];
        foreach ($widgets as $widget) {
            if ($widget instanceof Widget) {
                $valid[] = $widget;
            }
        }

        $this->widgets = $valid;

        return $this;
    }

    public function addWidget(Widget $widget): self
    {
        $this->widgets[] = $widget;

        return $this;
    }

    /**
     * Number of columns in the dashboard grid (1..12). Widget
     * `columnSpan` values are interpreted relative to this.
     */
    public function columns(int $columns): self
    {
        $this->columns = max(1, min(12, $columns));

        return $this;
    }

    public function heading(string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function canSee(Closure $callback): self
    {
        $this->canSee = $callback;

        return $this;
    }

    public function canBeSeenBy(?Authenticatable $user): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($user);
    }

    /** @return list<Widget> */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Serialise the dashboard for the Inertia payload. Widgets
     * are filtered through `canBeSeenBy($user)` and sorted by
     * their `sort` value (null sorts last). Deferred widgets keep
     * `data: null` so the client lazy-fetches.
     *
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        $visible = [];
        foreach ($this->widgets as $widget) {
            if ($widget->canBeSeenBy($user)) {
                $visible[] = $widget;
            }
        }

        usort(
            $visible,
            fn (Widget $a, Widget $b): int => ($a->getSort() ?? PHP_INT_MAX) <=> ($b->getSort() ?? PHP_INT_MAX),
        );

        return [
            'columns' => $this->columns,
            'heading' => $this->heading,
            'description' => $this->description,
            'widgets' => array_map(
                fn (Widget $widget): array => $widget->toArray($user),
                $visible,
            ),
        ];
    }
}
