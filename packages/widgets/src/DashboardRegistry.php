<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use InvalidArgumentException;

/**
 * Singleton registry of `Dashboard` instances keyed by `Dashboard::$id`.
 *
 * Multi-dashboard apps register every dashboard at panel boot; the
 * controller layer reads the map to render the dashboard switcher
 * and resolve `/dashboards/{id}` routes.
 *
 * Re-registering the same id throws `InvalidArgumentException`
 * rather than silently overwriting — collisions almost always mean
 * a copy/paste bug in user code.
 */
final class DashboardRegistry
{
    /** @var array<string, Dashboard> */
    private array $dashboards = [];

    public function register(Dashboard $dashboard): void
    {
        if (isset($this->dashboards[$dashboard->id])) {
            throw new InvalidArgumentException(sprintf(
                'DashboardRegistry already has a dashboard registered with id [%s].',
                $dashboard->id,
            ));
        }

        $this->dashboards[$dashboard->id] = $dashboard;
    }

    public function has(string $id): bool
    {
        return isset($this->dashboards[$id]);
    }

    public function get(string $id): ?Dashboard
    {
        return $this->dashboards[$id] ?? null;
    }

    /**
     * @return array<string, Dashboard>
     */
    public function all(): array
    {
        return $this->dashboards;
    }

    public function clear(): void
    {
        $this->dashboards = [];
    }
}
