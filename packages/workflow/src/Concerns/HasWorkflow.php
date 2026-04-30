<?php

declare(strict_types=1);

namespace Arqel\Workflow\Concerns;

use Arqel\Workflow\Events\StateTransitioned;
use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\WorkflowDefinition;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
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
     * Transition the model to a new state and fire `StateTransitioned`.
     *
     * Captures the current state key (resolved via {@see resolveStateKey()}),
     * delegates the actual mutation to spatie's `transitionTo()` if it exists
     * on the underlying state object, otherwise assigns the new value directly
     * to the workflow field. Emits `StateTransitioned` so user-land listeners
     * can run audit log + notifications + broadcast (WF-004).
     *
     * The audit event is suppressed when `arqel-workflow.audit.enabled` is
     * `false`, allowing apps to opt-out per environment.
     *
     * @param  string  $newState  FQCN of the new state class, an enum value or a slug.
     * @param  array<string, mixed>  $context  Arbitrary metadata propagated to listeners.
     */
    public function transitionTo(string $newState, array $context = []): void
    {
        $definition = $this->arqelWorkflow();
        $field = $definition->getField();

        /** @var mixed $currentValue */
        $currentValue = $this->{$field} ?? null;
        $fromKey = self::resolveStateKey($currentValue) ?? '';

        // Prefer the state object's own `transitionTo()` (spatie API) when
        // available — keeps spatie guards/casts intact. Otherwise fall back
        // to a plain attribute assignment so the trait stays standalone.
        assert($this instanceof Model);

        if (is_object($currentValue) && method_exists($currentValue, 'transitionTo')) {
            $currentValue->transitionTo($newState);
        } else {
            $this->{$field} = $newState;
            $this->save();
        }

        if (config('arqel-workflow.audit.enabled', true) === false) {
            return;
        }

        if (config('arqel-workflow.audit.log_via', 'event') !== 'event') {
            return;
        }

        $userId = Auth::id();

        event(new StateTransitioned(
            record: $this,
            from: $fromKey,
            to: $newState,
            userId: is_int($userId) ? $userId : null,
            context: $context,
        ));
    }

    /**
     * Histórico append-only de transições deste record (WF-007).
     *
     * Persistido pelo listener `PersistStateTransitionToHistory` quando
     * `arqel-workflow.history.enabled` está ativo. Ordenado por
     * `created_at` desc para uso direto em UIs de timeline.
     *
     * @return MorphMany<StateTransition, $this>
     */
    public function stateTransitions(): MorphMany
    {
        assert($this instanceof Model);

        /** @var MorphMany<StateTransition, $this> $relation */
        $relation = $this->morphMany(StateTransition::class, 'model')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        return $relation;
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
