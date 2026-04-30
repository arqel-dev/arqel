<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Concerns;

use Arqel\Core\CommandPalette\CommandRegistry;

/**
 * Trait for user-land providers that want a single dedicated entry
 * point for declaring custom commands.
 *
 * Typical usage in an application service provider:
 *
 *     final class AppCommandsProvider
 *     {
 *         use HasCustomCommands;
 *
 *         public function commands(CommandRegistry $registry): void
 *         {
 *             $registry->registerStatic(
 *                 id: 'cache:clear',
 *                 label: 'Clear application cache',
 *                 url: '/admin/system/cache-clear',
 *                 category: 'System',
 *                 icon: 'refresh-cw',
 *             );
 *         }
 *     }
 *
 * The trait deliberately ships with a no-op default — subclasses
 * (or consumers) override `commands()` and call it explicitly from
 * their own boot path. This keeps the trait dependency-free: it
 * never touches the container, never registers anything globally
 * and never assumes a Laravel boot phase.
 */
trait HasCustomCommands
{
    /**
     * Register custom commands with the supplied registry.
     *
     * Default is a no-op; override in classes that use the trait.
     */
    public function commands(CommandRegistry $registry): void
    {
        // no-op by default — override in user code
    }
}
