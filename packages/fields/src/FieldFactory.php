<?php

declare(strict_types=1);

namespace Arqel\Fields;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;

/**
 * Static factory and registry for Arqel field types.
 *
 * Concrete factory methods (`text()`, `email()`, `select()`, …) are
 * added as each Field type lands (FIELDS-004 onwards). Today this
 * class hosts the cross-cutting infrastructure shared by all types:
 *
 * - `register(string $type, class-string<Field>)` exposes a third-party
 *   field as `FieldFactory::$type($name)`. Required for RF-F-07.
 * - `macro(string $name, Closure)` lets apps define one-line shortcuts
 *   on top of existing fields (`FieldFactory::priceBRL('price')`).
 *   Required for RF-F-08.
 * - `__callStatic` looks up macros first, then the registry, and
 *   raises a clear error when neither matches.
 *
 * The class is named `FieldFactory` (not `Field`) to avoid colliding
 * with the abstract base class. The public ergonomic alias
 * (`Field::text(...)`) is provided by the service provider once the
 * concrete types ship.
 */
final class FieldFactory
{
    /** @var array<string, class-string<Field>> */
    private static array $registry = [];

    /** @var array<string, Closure> */
    private static array $macros = [];

    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (isset(self::$macros[$name])) {
            return (self::$macros[$name])(...$arguments);
        }

        if (isset(self::$registry[$name])) {
            $fieldClass = self::$registry[$name];

            return new $fieldClass(...$arguments);
        }

        throw new BadMethodCallException(
            "Field type or macro [{$name}] is not registered. ".
            'Use FieldFactory::register() or FieldFactory::macro() first.',
        );
    }

    /**
     * Register a concrete Field type so it can be instantiated via
     * `FieldFactory::{$type}($name)`.
     *
     * @param class-string<Field> $fieldClass
     *
     * @throws InvalidArgumentException when $fieldClass is not a Field subclass
     */
    public static function register(string $type, string $fieldClass): void
    {
        if (! is_subclass_of($fieldClass, Field::class)) {
            throw new InvalidArgumentException(
                "Field class [{$fieldClass}] must extend ".Field::class.'.',
            );
        }

        self::$registry[$type] = $fieldClass;
    }

    public static function hasType(string $type): bool
    {
        return isset(self::$registry[$type]);
    }

    /**
     * Define a one-line shortcut on top of existing fields.
     *
     * The callback receives the same arguments passed to the macro
     * call — typically the field name plus any extras — and must
     * return a `Field` instance.
     */
    public static function macro(string $name, Closure $callback): void
    {
        self::$macros[$name] = $callback;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(self::$macros[$name]);
    }

    /**
     * @return array<string, class-string<Field>>
     */
    public static function getRegisteredTypes(): array
    {
        return self::$registry;
    }

    /**
     * @return array<int, string>
     */
    public static function getRegisteredMacros(): array
    {
        $names = array_keys(self::$macros);
        sort($names);

        return $names;
    }

    /**
     * Forget all registered types and macros.
     *
     * Intended for tests; production code should not need to call this.
     */
    public static function flush(): void
    {
        self::$registry = [];
        self::$macros = [];
    }
}
