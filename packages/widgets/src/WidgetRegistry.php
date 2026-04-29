<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use InvalidArgumentException;

/**
 * Singleton registry of available widget classes by type
 * identifier. Apps register custom widget classes via
 * `WidgetRegistry::register('my-type', MyWidget::class)`.
 *
 * Used primarily by the dashboard discovery flow and by tooling
 * (e.g. `arqel:widget` command) — the runtime instantiation goes
 * through `new MyWidget('name')` directly, not via the registry.
 */
final class WidgetRegistry
{
    /** @var array<string, class-string<Widget>> */
    private array $widgets = [];

    /**
     * Register a widget class under a stable string type. Validates
     * that the class extends `Widget`.
     *
     * @param class-string<Widget> $widgetClass
     */
    public function register(string $type, string $widgetClass): void
    {
        if (! is_subclass_of($widgetClass, Widget::class)) {
            throw new InvalidArgumentException(sprintf(
                'WidgetRegistry::register expected class-string<%s>, got [%s].',
                Widget::class,
                $widgetClass,
            ));
        }

        $this->widgets[$type] = $widgetClass;
    }

    public function has(string $type): bool
    {
        return isset($this->widgets[$type]);
    }

    /**
     * @return class-string<Widget>|null
     */
    public function get(string $type): ?string
    {
        return $this->widgets[$type] ?? null;
    }

    /**
     * @return array<string, class-string<Widget>>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    public function clear(): void
    {
        $this->widgets = [];
    }
}
