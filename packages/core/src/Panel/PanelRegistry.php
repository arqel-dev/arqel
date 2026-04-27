<?php

declare(strict_types=1);

namespace Arqel\Core\Panel;

/**
 * Singleton registry for Arqel panels.
 *
 * `panel($id)` is intentionally create-or-get: calling it with the
 * same id returns the existing builder so configuration can be
 * spread across multiple files (e.g. several service providers
 * contributing resources to the same admin panel).
 */
final class PanelRegistry
{
    /**
     * @var array<string, Panel>
     */
    private array $panels = [];

    private ?string $currentPanelId = null;

    public function panel(string $id): Panel
    {
        return $this->panels[$id] ??= new Panel($id);
    }

    public function has(string $id): bool
    {
        return isset($this->panels[$id]);
    }

    public function getCurrent(): ?Panel
    {
        return $this->currentPanelId === null
            ? null
            : ($this->panels[$this->currentPanelId] ?? null);
    }

    public function setCurrent(string $id): void
    {
        if (! isset($this->panels[$id])) {
            throw new PanelNotFoundException($id);
        }

        $this->currentPanelId = $id;
    }

    /**
     * @return array<int, Panel>
     */
    public function all(): array
    {
        return array_values($this->panels);
    }

    public function clear(): void
    {
        $this->panels = [];
        $this->currentPanelId = null;
    }
}
