<?php

declare(strict_types=1);

namespace Arqel\Workflow;

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
            ->hasConfigFile('arqel-workflow');
    }
}
