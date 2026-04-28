<?php

declare(strict_types=1);

namespace Arqel\Fields\Concerns;

use Closure;

/**
 * Reactive field dependencies (RF-F-06).
 *
 * `dependsOn(['country_id'])` declares which sibling fields this
 * field watches; `resolveOptionsUsing(Closure)` returns the new
 * options when one of the dependencies changes (typical use case
 * for cascading selects: country → state → city).
 *
 * `handleDependencyUpdate($formState, $changedField)` is the
 * server-side oracle the controller (CORE-006) will call when the
 * client posts a partial reload. Today it's stand-alone — the
 * React side will trigger it via a `POST /fields/{field}/refresh`
 * endpoint added when the controller ships.
 *
 * The Field base owns `afterStateUpdated`; this trait adds the
 * reactive dependency set on top.
 */
trait HasDependencies
{
    /** @var array<int, string> */
    protected array $dependencies = [];

    protected ?Closure $resolveOptionsCallback = null;

    /**
     * @param string|array<int, string> $fields
     */
    public function dependsOn(string|array $fields): static
    {
        $list = is_array($fields) ? $fields : [$fields];

        foreach ($list as $field) {
            if (! in_array($field, $this->dependencies, true)) {
                $this->dependencies[] = $field;
            }
        }

        return $this;
    }

    public function resolveOptionsUsing(Closure $callback): static
    {
        $this->resolveOptionsCallback = $callback;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function hasDependencies(): bool
    {
        return $this->dependencies !== [];
    }

    public function getResolveOptionsCallback(): ?Closure
    {
        return $this->resolveOptionsCallback;
    }

    /**
     * Resolve fresh options based on the current form state.
     *
     * `$formState` is a flat `field name => current value` map; the
     * `$changedField` lets the callback short-circuit when the
     * change came from a field it does not care about. Returns an
     * empty array when no resolver is registered.
     *
     * @param array<string, mixed> $formState
     *
     * @return array<int|string, mixed>
     */
    public function handleDependencyUpdate(array $formState, string $changedField): array
    {
        if ($this->resolveOptionsCallback === null) {
            return [];
        }

        if ($this->dependencies !== [] && ! in_array($changedField, $this->dependencies, true)) {
            return [];
        }

        $resolved = ($this->resolveOptionsCallback)($formState, $changedField);

        return is_array($resolved) ? $resolved : [];
    }
}
