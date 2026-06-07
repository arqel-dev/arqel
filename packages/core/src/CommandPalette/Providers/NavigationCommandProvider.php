<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Providers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\ResourceAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Built-in command provider that emits one navigation Command per
 * registered Resource.
 *
 * For every class returned by {@see ResourceRegistry::all()} we
 * synthesise a Command shaped like:
 *
 *     id       = "nav:{slug}"
 *     label    = "Go to {plural label}"
 *     url      = "/admin/{slug}"
 *     category = "Navigation"
 *     icon     = $resource::getNavigationIcon()
 *
 * Each metadata read is wrapped in a defensive try/catch so a
 * misbehaving Resource (throwing inside `getSlug()` etc.) never
 * brings down the whole palette — the offending entry is silently
 * skipped, the rest still ship.
 *
 * The `$user` is consulted to gate navigation by the `viewAny` Policy:
 * a Resource the user is denied `viewAny` on is skipped, so the palette
 * never lists a "Go to" shortcut for a feature the sidebar already hides
 * (issue #129 — symmetric with the #118 sidebar fix). Both surfaces share
 * one guard, {@see ResourceAuthorization::viewAnyDenied()}. When no gate
 * or policy exists (scaffold apps) every Resource is listed, so the
 * no-policy baseline is unchanged. The `string $query` parameter is
 * accepted to satisfy the {@see CommandProvider} contract but not used
 * here: query filtering is the registry's job (FuzzyMatcher).
 */
final class NavigationCommandProvider implements CommandProvider
{
    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    /**
     * @return array<int, Command>
     */
    public function provide(?Authenticatable $user, string $query): array
    {
        $commands = [];

        foreach ($this->registry->all() as $resourceClass) {
            if (ResourceAuthorization::viewAnyDenied($resourceClass, $user)) {
                continue;
            }

            $command = $this->buildCommand($resourceClass);

            if ($command !== null) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * Synthesise a navigation Command for a single Resource class.
     *
     * Slug + plural label are required (without them the entry is
     * meaningless); icon is optional and any failure to read it
     * downgrades to `null` instead of dropping the command.
     *
     * @param class-string $resourceClass
     */
    private function buildCommand(string $resourceClass): ?Command
    {
        try {
            /** @var string $slug */
            $slug = $resourceClass::getSlug();
        } catch (Throwable) {
            return null;
        }

        try {
            /** @var string $pluralLabel */
            $pluralLabel = $resourceClass::getPluralLabel();
        } catch (Throwable) {
            return null;
        }

        $icon = null;

        try {
            /** @var string|null $icon */
            $icon = $resourceClass::getNavigationIcon();
        } catch (Throwable) {
            $icon = null;
        }

        return new Command(
            id: 'nav:'.$slug,
            label: 'Go to '.$pluralLabel,
            url: '/admin/'.$slug,
            description: null,
            category: 'Navigation',
            icon: $icon,
        );
    }
}
