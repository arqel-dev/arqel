<?php

declare(strict_types=1);

namespace Arqel\Workflow;

use Arqel\Workflow\Events\StateTransitioned;
use Arqel\Workflow\Listeners\PersistStateTransitionToHistory;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/workflow`.
 *
 * WF-001 ships the package skeleton:
 *
 * - publishable `config/arqel-workflow.php` with default color/icon
 *   fallbacks consumed by `WorkflowDefinition::getStateMetadata()`.
 * - boot is intentionally minimal; the `StateTransitionField`,
 *   `TransitionController` and React visualizer land in WF-003+.
 *
 * WF-007 adiciona: migration `arqel_state_transitions` + listener
 * `PersistStateTransitionToHistory` registado automaticamente quando a
 * flag `arqel-workflow.history.enabled` está ativa.
 *
 * The package does NOT hard-depend on `spatie/laravel-model-states`; the
 * dependency is `suggest:` only and `HasWorkflow` duck-types both states
 * (any class-string or stringable token) and transitions (any class with
 * an optional static `from(): array` method).
 */
final class WorkflowServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-workflow')
            ->hasConfigFile('arqel-workflow')
            ->hasMigration('create_arqel_state_transitions_table');
    }

    public function packageBooted(): void
    {
        if (config('arqel-workflow.history.enabled', true) === false) {
            return;
        }

        Event::listen(StateTransitioned::class, PersistStateTransitionToHistory::class);
    }
}
