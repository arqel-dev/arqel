<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Providers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Arqel\Core\Resources\ResourceRegistry;
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
 * The `?Authenticatable $user` and `string $query` parameters are
 * accepted to satisfy the {@see CommandProvider} contract but are
 * not consulted here: query filtering is the registry's job
 * (FuzzyMatcher) and we do not yet gate navigation by Policy.
 * Policy-gated providers (CreateCommandProvider, RecordSearchProvider)
 * are deferred to follow-up work.
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
