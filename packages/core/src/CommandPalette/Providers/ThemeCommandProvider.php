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
        // Labels + category are localised lazily here (at provide() time) so
        // the active request locale applies. Under the default `en` locale the
        // key values equal the original English literals, so the palette's
        // accessible names stay stable.
        $category = (string) __('arqel::palette.category.settings');

        return [
            new Command(
                id: 'theme:light',
                label: (string) __('arqel::palette.theme.light'),
                url: '?theme=light',
                description: null,
                category: $category,
                icon: 'sun',
            ),
            new Command(
                id: 'theme:dark',
                label: (string) __('arqel::palette.theme.dark'),
                url: '?theme=dark',
                description: null,
                category: $category,
                icon: 'moon',
            ),
            new Command(
                id: 'theme:system',
                label: (string) __('arqel::palette.theme.system'),
                url: '?theme=system',
                description: null,
                category: $category,
                icon: 'monitor',
            ),
        ];
    }
}
