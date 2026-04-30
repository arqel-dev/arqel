<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

/**
 * Immutable value-object describing a single command palette entry.
 *
 * Commands are registered statically (always-on) or produced lazily
 * by a {@see CommandProvider}. Both paths funnel through the same
 * shape so the React palette can render them uniformly.
 *
 * @phpstan-type CommandArray array{
 *     id: string,
 *     label: string,
 *     url: string,
 *     description: string|null,
 *     category: string|null,
 *     icon: string|null,
 * }
 */
final readonly class Command
{
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public ?string $description = null,
        public ?string $category = null,
        public ?string $icon = null,
    ) {}

    /**
     * @return CommandArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
        ];
    }
}
