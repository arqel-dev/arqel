<?php

declare(strict_types=1);

namespace Arqel\Core\Resources;

use Arqel\Core\Contracts\HasResource;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Singleton registry for Arqel Resource classes.
 *
 * Resources are stored by their FQCN. Lookups by model class or slug
 * are O(n) and intentionally unindexed: the working set per panel is
 * small (dozens, not thousands) and the linear scan keeps registration
 * cheap. Promote to indexed maps if profiling shows otherwise.
 */
final class ResourceRegistry
{
    /**
     * @var array<class-string<HasResource>, class-string<HasResource>>
     */
    private array $resources = [];

    /**
     * Register a single Resource class.
     *
     * Idempotent: registering the same class twice is a no-op.
     *
     * @throws InvalidArgumentException when the class does not implement HasResource
     */
    public function register(string $resourceClass): void
    {
        if (! is_subclass_of($resourceClass, HasResource::class)) {
            throw new InvalidArgumentException(
                "Resource [{$resourceClass}] must implement ".HasResource::class.'.',
            );
        }

        $this->resources[$resourceClass] = $resourceClass;
    }

    /**
     * Register a list of Resource classes.
     *
     * @param array<int, class-string<HasResource>|string> $resourceClasses
     */
    public function registerMany(array $resourceClasses): void
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->register($resourceClass);
        }
    }

    /**
     * Scan a directory for classes implementing HasResource and register them.
     *
     * Only classes already autoloadable under the given namespace are
     * considered: we do not include or eval files. This relies on the
     * application's PSR-4 autoloader being correctly configured.
     */
    public function discover(string $path, string $namespace): void
    {
        if (! is_dir($path)) {
            return;
        }

        $finder = (new Finder)
            ->files()
            ->in($path)
            ->name('*.php')
            ->depth('>= 0');

        $namespace = rtrim($namespace, '\\');

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $classPath = substr($relative, 0, -4);
            $fqcn = $namespace.'\\'.str_replace('/', '\\', $classPath);

            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if (
                $reflection->isAbstract()
                || $reflection->isInterface()
                || $reflection->isTrait()
            ) {
                continue;
            }

            if (! $reflection->implementsInterface(HasResource::class)) {
                continue;
            }

            /** @var class-string<HasResource> $fqcn */
            $this->register($fqcn);
        }
    }

    /**
     * @return array<int, class-string<HasResource>>
     */
    public function all(): array
    {
        return array_values($this->resources);
    }

    /**
     * Find the Resource class managing the given model class, if any.
     *
     * @return class-string<HasResource>|null
     */
    public function findByModel(string $modelClass): ?string
    {
        foreach ($this->resources as $resourceClass) {
            if ($resourceClass::getModel() === $modelClass) {
                return $resourceClass;
            }
        }

        return null;
    }

    /**
     * Find the Resource class with the given slug, if any.
     *
     * @return class-string<HasResource>|null
     */
    public function findBySlug(string $slug): ?string
    {
        foreach ($this->resources as $resourceClass) {
            if ($resourceClass::getSlug() === $slug) {
                return $resourceClass;
            }
        }

        return null;
    }

    public function has(string $resourceClass): bool
    {
        return isset($this->resources[$resourceClass]);
    }

    public function clear(): void
    {
        $this->resources = [];
    }
}
