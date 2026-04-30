<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Providers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Built-in command provider that emits the three theme-switch
 * commands (light / dark / system).
 *
 * The set is fully static — the provider always returns the same
 * three Commands regardless of user or query. Filtering by `q` is
 * the registry's responsibility (via {@see \Arqel\Core\CommandPalette\FuzzyMatcher}),
 * so the provider stays simple and side-effect-free.
 *
 * The URLs are query-string toggles (`?theme=...`) consumed by the
 * frontend theme bootstrapper. They are intentionally relative so
 * they merge with whatever page the user is on when they invoke
 * the palette.
 */
final class ThemeCommandProvider implements CommandProvider
{
    /**
     * @return array<int, Command>
     */
    public function provide(?Authenticatable $user, string $query): array
    {
        return [
            new Command(
                id: 'theme:light',
                label: 'Switch to light theme',
                url: '?theme=light',
                description: null,
                category: 'Settings',
                icon: 'sun',
            ),
            new Command(
                id: 'theme:dark',
                label: 'Switch to dark theme',
                url: '?theme=dark',
                description: null,
                category: 'Settings',
                icon: 'moon',
            ),
            new Command(
                id: 'theme:system',
                label: 'Use system theme',
                url: '?theme=system',
                description: null,
                category: 'Settings',
                icon: 'monitor',
            ),
        ];
    }
}
