<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * Singleton registry for command palette entries.
 *
 * Two flavours of source converge here:
 *   - `register(Command)` — always-on commands resolved before any
 *     provider runs (logout, theme toggle, etc.)
 *   - `registerProvider(CommandProvider|Closure)` — lazy providers
 *     queried on every request with the active user + query
 *
 * Ergonomic sugar for user-land registration:
 *   - `registerStatic(id, label, url, ...)` — build + register a
 *     `Command` in one call. Re-using an id throws to surface
 *     duplicates instead of silently shadowing entries.
 *   - `registerClosureProvider(Closure)` — explicit, readable
 *     entry-point for closure-based providers.
 *
 * `resolveFor` merges both, applies the per-command auth filter
 * (`requiresAuth` / `hideForAuthenticated`), fuzzy-filters against
 * the query and caps the result at {@see FuzzyMatcher::LIMIT}.
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
     * Convenience wrapper around {@see register()} for the common
     * "static command with a URL" case. Re-registering the same id
     * throws an {@see InvalidArgumentException} so accidental
     * duplicates surface immediately instead of silently shadowing.
     */
    public function registerStatic(
        string $id,
        string $label,
        string $url,
        ?string $description = null,
        ?string $category = null,
        ?string $icon = null,
    ): void {
        foreach ($this->commands as $existing) {
            if ($existing->id === $id) {
                throw new InvalidArgumentException("Command id '{$id}' already registered");
            }
        }

        $this->register(new Command(
            id: $id,
            label: $label,
            url: $url,
            description: $description,
            category: $category,
            icon: $icon,
        ));
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
     * Sugar over {@see registerProvider()} for the closure path.
     *
     * Reads better at the call site (`registerClosureProvider(fn ...)`
     * vs `registerProvider(fn ...)`) and keeps user-land registration
     * intent explicit.
     *
     * @param Closure(?Authenticatable, string): array<int, Command> $closure
     */
    public function registerClosureProvider(Closure $closure): void
    {
        $this->registerProvider($closure);
    }

    /**
     * Merge static commands with everything providers contribute,
     * apply the per-command auth filter, then run the result through
     * the fuzzy filter.
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

        $visible = array_values(array_filter(
            $merged,
            static fn (Command $c): bool => self::isVisibleTo($c, $user),
        ));

        return FuzzyMatcher::rank($visible, $query);
    }

    /**
     * Auth-aware visibility check. Defaults (both flags null) keep
     * the command visible to everyone; explicit `true` flags filter
     * by the current user state.
     */
    private static function isVisibleTo(Command $command, ?Authenticatable $user): bool
    {
        if ($command->requiresAuth === true && $user === null) {
            return false;
        }

        if ($command->hideForAuthenticated === true && $user !== null) {
            return false;
        }

        return true;
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

    /**
     * Lazy providers in registration order.
     *
     * Exposed primarily for tests and introspection — runtime code
     * should go through {@see resolveFor()} instead of poking at the
     * provider list directly.
     *
     * @return array<int, CommandProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function clear(): void
    {
        $this->commands = [];
        $this->providers = [];
    }
}
