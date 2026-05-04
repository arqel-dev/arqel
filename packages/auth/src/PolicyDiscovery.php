<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Discovers Laravel Policies for Arqel Resources.
 *
 * Laravel 11+ already auto-resolves `App\Models\Foo` →
 * `App\Policies\FooPolicy`; `PolicyDiscovery` adds two things on
 * top of that:
 *
 * 1. Verifies a Policy exists per registered Resource and emits a
 *    log warning when missing (silent in production unless the
 *    `arqel.auth.warn_missing_policies` config flag is on).
 * 2. Honours `Resource::$policy` overrides by registering them
 *    explicitly with the Gate.
 *
 * The resource's contract is duck-typed (`getModel(): ?string` +
 * optional public static `$policy`) to avoid circular dependency
 * with `arqel-dev/core` at type-check time.
 */
final class PolicyDiscovery
{
    public function __construct(
        protected Container $container,
        protected LoggerInterface $logger,
    ) {}

    /**
     * @param array<int, class-string> $resources Resource class-strings
     *
     * @return array{registered: array<class-string, class-string>, missing: array<int, class-string>}
     */
    public function autoRegisterPoliciesFor(array $resources): array
    {
        $registered = [];
        $missing = [];

        foreach ($resources as $resourceClass) {
            $modelClass = $this->resolveModel($resourceClass);
            if ($modelClass === null) {
                continue;
            }

            $policyClass = $this->resolvePolicy($resourceClass, $modelClass);
            if ($policyClass === null) {
                $missing[] = $resourceClass;
                $this->logger->warning(
                    "Arqel: no Policy found for resource {$resourceClass} (model {$modelClass}).",
                );

                continue;
            }

            Gate::policy($modelClass, $policyClass);
            $registered[$modelClass] = $policyClass;
        }

        return ['registered' => $registered, 'missing' => $missing];
    }

    /**
     * @param class-string $resourceClass
     *
     * @return class-string|null
     */
    protected function resolveModel(string $resourceClass): ?string
    {
        if (! class_exists($resourceClass)) {
            return null;
        }

        if (! method_exists($resourceClass, 'getModel')) {
            return null;
        }

        try {
            /** @var class-string|null $model */
            $model = $resourceClass::getModel();
        } catch (Throwable) {
            return null;
        }

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * @param class-string $resourceClass
     * @param class-string $modelClass
     *
     * @return class-string|null
     */
    protected function resolvePolicy(string $resourceClass, string $modelClass): ?string
    {
        if (property_exists($resourceClass, 'policy')) {
            /** @var class-string|null $explicit */
            $explicit = $resourceClass::$policy ?? null;
            if (is_string($explicit) && class_exists($explicit)) {
                return $explicit;
            }
        }

        $candidate = $this->guessPolicyFromModel($modelClass);

        return $candidate !== null && class_exists($candidate) ? $candidate : null;
    }

    /**
     * @param class-string $modelClass
     *
     * @return class-string|null
     */
    protected function guessPolicyFromModel(string $modelClass): ?string
    {
        if (! str_contains($modelClass, '\\Models\\')) {
            return null;
        }

        $candidate = str_replace('\\Models\\', '\\Policies\\', $modelClass).'Policy';

        /** @var class-string $candidate */
        return $candidate;
    }
}
