<?php

declare(strict_types=1);

namespace Arqel\Workflow\Concerns;

use Arqel\Workflow\WorkflowDefinition;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use ReflectionException;
use ReflectionMethod;

/**
 * Trait applied by user-land Eloquent models to expose a workflow.
 *
 * Designed to be **agnostic** of the underlying state library:
 *
 * - the model declares `arqelWorkflow(): WorkflowDefinition` returning
 *   the metadata (states + transitions);
 * - the persisted state lives at `$model->{$definition->getField()}` —
 *   it can be a `Spatie\ModelStates\State` instance, a PHP enum, a raw
 *   string slug, or anything else with a sensible string/class-string
 *   identity. We never invoke library-specific methods.
 *
 * Consumers:
 *
 * @method WorkflowDefinition arqelWorkflow()
 *
 * @phpstan-require-extends Model
 */
trait HasWorkflow
{
    /**
     * Return the metadata of the model's *current* state.
     *
     * Reads `$this->{$definition->getField()}`, resolves the state's
     * identity (FQCN if it is an object, value if it is a `BackedEnum`,
     * the string itself otherwise) and looks the metadata up via
     * `WorkflowDefinition::getStateMetadata()`. Returns `null` when the
     * field is empty or the resolved key is not registered.
     *
     * @return array{label: string, color: string, icon: string}|null
     */
    public function getCurrentStateMetadata(): ?array
    {
        $definition = $this->arqelWorkflow();
        $field = $definition->getField();

        // Resolve the field value defensively: an Eloquent attribute may
        // not be set yet (fresh model) or might not be cast.
        /** @var mixed $value */
        $value = $this->{$field} ?? null;

        $key = self::resolveStateKey($value);

        if ($key === null) {
            return null;
        }

        return $definition->getStateMetadata($key);
    }

    /**
     * Return the list of transition classes whose `from()` includes the
     * model's current state. A transition without a static `from()`
     * method is included by default (treated as "always available").
     *
     * @return list<class-string>
     */
    public function getAvailableTransitions(): array
    {
        $definition = $this->arqelWorkflow();
        $current = self::resolveStateKey($this->{$definition->getField()} ?? null);

        $available = [];

        foreach ($definition->getTransitions() as $transition) {
            if (! self::transitionApplies($transition, $current)) {
                continue;
            }

            $available[] = $transition;
        }

        return $available;
    }

    /**
     * Resolve the canonical key for a state value:
     * - object → its FQCN (matches spatie/laravel-model-states which
     *   keys metadata by `State::class`)
     * - `BackedEnum` → its `value`
     * - non-empty string → itself
     * - anything else → `null`
     */
    private static function resolveStateKey(mixed $value): ?string
    {
        if (is_object($value)) {
            if ($value instanceof BackedEnum) {
                return (string) $value->value;
            }

            return $value::class;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Duck-typed eligibility check. A transition class without a static
     * `from()` method is considered always-available; otherwise the
     * current state key must appear in its `from()` list.
     *
     * @param  class-string  $transition
     */
    private static function transitionApplies(string $transition, ?string $current): bool
    {
        if (! class_exists($transition)) {
            return false;
        }

        if (! method_exists($transition, 'from')) {
            return true;
        }

        try {
            $reflection = new ReflectionMethod($transition, 'from');
        } catch (ReflectionException) {
            return false;
        }

        if (! $reflection->isStatic() || ! $reflection->isPublic()) {
            return true;
        }

        /** @var mixed $from */
        $from = $reflection->invoke(null);

        if (! is_array($from)) {
            return true;
        }

        if ($current === null) {
            return false;
        }

        foreach ($from as $candidate) {
            if (is_string($candidate) && $candidate === $current) {
                return true;
            }
        }

        return false;
    }
}
