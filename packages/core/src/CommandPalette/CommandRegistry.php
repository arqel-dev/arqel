<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Singleton registry for command palette entries.
 *
 * Two flavours of source converge here:
 *   - `register(Command)` — always-on commands resolved before any
 *     provider runs (logout, theme toggle, etc.)
 *   - `registerProvider(CommandProvider|Closure)` — lazy providers
 *     queried on every request with the active user + query
 *
 * `resolveFor` merges both, fuzzy-filters against the query and
 * caps the result at {@see FuzzyMatcher::LIMIT}. Built-in providers
 * (Navigation/Create/RecordSearch/Theme) are deferred to CMDPAL-002.
 */
final class CommandRegistry
{
    /** @var array<int, Command> */
    private array $commands = [];

    /** @var array<int, CommandProvider> */
    private array $providers = [];

    public function register(Command $command): void
    {
        $this->commands[] = $command;
    }

    /**
     * Accept either a `CommandProvider` instance or a closure with
     * the same signature. Closures get wrapped in an anonymous
     * adapter so the call site of `resolveFor` only deals with one
     * concrete shape.
     *
     * @param CommandProvider|Closure(?Authenticatable, string): array<int, Command> $provider
     */
    public function registerProvider(CommandProvider|Closure $provider): void
    {
        if ($provider instanceof Closure) {
            $provider = new readonly class($provider) implements CommandProvider
            {
                /** @param Closure(?Authenticatable, string): array<int, Command> $callback */
                public function __construct(private Closure $callback) {}

                public function provide(?Authenticatable $user, string $query): array
                {
                    return ($this->callback)($user, $query);
                }
            };
        }

        $this->providers[] = $provider;
    }

    /**
     * Merge static commands with everything providers contribute,
     * then run the result through the fuzzy filter.
     *
     * @return array<int, Command>
     */
    public function resolveFor(?Authenticatable $user, string $query): array
    {
        $merged = $this->commands;

        foreach ($this->providers as $provider) {
            foreach ($provider->provide($user, $query) as $command) {
                $merged[] = $command;
            }
        }

        return FuzzyMatcher::rank($merged, $query);
    }

    /**
     * Statically registered commands, in registration order.
     *
     * Does NOT include commands produced by providers — those are
     * only known per-request via `resolveFor`.
     *
     * @return array<int, Command>
     */
    public function all(): array
    {
        return $this->commands;
    }

    public function clear(): void
    {
        $this->commands = [];
        $this->providers = [];
    }
}
