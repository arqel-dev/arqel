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
 * The two optional auth flags (`$requiresAuth` and `$hideForAuthenticated`)
 * let registration sites express simple visibility rules without
 * wrapping the command in a custom provider:
 *
 *   - `requiresAuth = true`  → only visible once a user is logged in
 *   - `hideForAuthenticated = true` → only visible to guests
 *   - both `null` (default) → always visible
 *
 * Filtering happens in {@see CommandRegistry::resolveFor()} after
 * the static + provider merge and before fuzzy ranking.
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
        public ?bool $requiresAuth = null,
        public ?bool $hideForAuthenticated = null,
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
