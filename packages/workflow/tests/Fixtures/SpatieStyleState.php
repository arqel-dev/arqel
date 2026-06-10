<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

/**
 * Minimal stub that mimics a `spatie/laravel-model-states` State object: it is
 * an object that owns its own `transitionTo()` method, which is exactly how
 * `HasWorkflow::transitionTo()` detects the spatie path
 * (`is_object($value) && method_exists($value, 'transitionTo')`).
 *
 * The stub records the target it was asked to transition to (and a flag that it
 * was invoked) so tests can assert whether spatie's own mutation was reached —
 * i.e. that authorization ran *before* delegating, blocking unauthorized
 * transitions before spatie ever fires.
 */
final class SpatieStyleState
{
    /**
     * Spy: the targets `transitionTo()` was invoked with, across all instances.
     * Because the cast hydrates a fresh object on every attribute read, an
     * instance property would be lost; a static collector lets a test prove
     * whether spatie's own mutation body was (or was not) reached.
     *
     * @var list<string>
     */
    public static array $transitions = [];

    public function __construct(public readonly string $name = PendingState::class) {}

    /**
     * `HasWorkflow::resolveStateKey()` keys an object by its FQCN. Overriding
     * the identity is not possible, but the metadata lookup uses the class name,
     * so fixtures register the state under this stub's FQCN.
     */
    public function __toString(): string
    {
        return $this->name;
    }

    public static function reset(): void
    {
        self::$transitions = [];
    }

    /**
     * Mimic spatie's `transitionTo()`: in spatie this performs the actual state
     * change. Here we only record that it ran so a test can prove that an
     * *unauthorized* transition never reaches this body, while an authorized one
     * does.
     */
    public function transitionTo(string $newState): void
    {
        self::$transitions[] = $newState;
    }
}
