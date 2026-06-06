<?php

declare(strict_types=1);

namespace Arqel\Workflow\Concerns;

use Arqel\Workflow\Authorization\TransitionAuthorizer;
use Arqel\Workflow\Events\StateTransitioned;
use Arqel\Workflow\Exceptions\IllegalTransitionException;
use Arqel\Workflow\Exceptions\UnauthorizedTransitionException;
use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\WorkflowDefinition;
use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use ReflectionException;
use ReflectionMethod;
use Throwable;

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
     * @param string $newState FQCN of the new state class, an enum value or a slug.
     * @param array<string, mixed> $context Arbitrary metadata propagated to listeners.
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
            // Spatie path: keep its own guards/casts intact — unchanged.
            $currentValue->transitionTo($newState);
        } else {
            // Fallback path (raw string / enum / slug). Enforce the
            // model's declared workflow graph + the TransitionAuthorizer
            // (WF-006) *before* mutating and persisting. Models that
            // declare no transitions stay free-form (no enforcement).
            $this->assertTransitionAllowed($definition, $fromKey, $newState);

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
     * @param class-string $transition
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

    /**
     * Enforce the declared workflow graph + the central authorizer before a
     * fallback-path transition mutates and persists (WF-006).
     *
     * Behaviour:
     * - When the model declares **no** transitions the workflow is free-form
     *   (mirrors the showcase `Ticket`): no eligibility nor authorization is
     *   imposed, any target state is accepted.
     * - Otherwise the target must be reachable: at least one declared
     *   transition whose `from()` includes the current state (or which is
     *   open / has no `from()`) must resolve to `$newState`. If none does,
     *   the transition is illegal.
     * - Among the matching transitions, at least one must be authorized by
     *   {@see TransitionAuthorizer::authorize()} for the current user +
     *   record. The authorizer is deny-by-default, mirroring the read-only
     *   UI flag in `StateTransitionField`.
     *
     * @throws IllegalTransitionException when no declared transition reaches the target
     * @throws UnauthorizedTransitionException when a path exists but the user is not allowed
     */
    private function assertTransitionAllowed(WorkflowDefinition $definition, string $fromKey, string $newState): void
    {
        $transitions = $definition->getTransitions();

        // No declared transitions => free-form workflow, no enforcement.
        if ($transitions === []) {
            return;
        }

        $currentKey = $fromKey === '' ? null : $fromKey;

        /** @var list<class-string> $matching */
        $matching = [];

        foreach ($transitions as $transition) {
            if (! self::transitionApplies($transition, $currentKey)) {
                continue;
            }

            if (! self::transitionTargets($transition, $newState)) {
                continue;
            }

            $matching[] = $transition;
        }

        if ($matching === []) {
            throw IllegalTransitionException::for($fromKey, $newState);
        }

        $user = self::resolveCurrentUser();

        foreach ($matching as $transition) {
            if (TransitionAuthorizer::authorize($transition, $user, $this)) {
                return;
            }
        }

        throw UnauthorizedTransitionException::for($newState);
    }

    /**
     * Whether the given transition declares `$newState` as its target.
     *
     * Comparison is done on the canonical slug used by the authorizer so a
     * transition declaring its `to` as a short token (e.g. derived from the
     * class name `PendingToPaid` => `Paid`) still matches a FQCN target such
     * as `App\States\PaidState`.
     *
     * @param class-string $transition
     */
    private static function transitionTargets(string $transition, string $newState): bool
    {
        $target = self::resolveTransitionTo($transition);

        if ($target === null) {
            return false;
        }

        return TransitionAuthorizer::slugifyState($target)
            === TransitionAuthorizer::slugifyState($newState);
    }

    /**
     * Resolve a transition's declared target state. Prefers a public static
     * `to()` method; otherwise derives it from the `XxxToYyy` class-name
     * convention. Mirrors `TransitionAuthorizer::resolveTo()`.
     *
     * @param class-string $transition
     */
    private static function resolveTransitionTo(string $transition): ?string
    {
        if (! class_exists($transition)) {
            return null;
        }

        if (method_exists($transition, 'to')) {
            try {
                $reflection = new ReflectionMethod($transition, 'to');

                if ($reflection->isStatic() && $reflection->isPublic()) {
                    /** @var mixed $result */
                    $result = $reflection->invoke(null);

                    if (is_string($result) && $result !== '') {
                        return $result;
                    }
                }
            } catch (Throwable) {
                // fall through to the class-name convention
            }
        }

        $short = str_contains($transition, '\\')
            ? (string) substr($transition, (int) strrpos($transition, '\\') + 1)
            : $transition;

        if (preg_match('/To([A-Z][A-Za-z0-9]*)$/', $short, $matches) === 1) {
            return $matches[1];
        }

        return $short;
    }

    /**
     * Resolve the acting user the same defensive way `StateTransitionField`
     * does: `Auth::user()` when the facade is bound, `null` otherwise.
     */
    private static function resolveCurrentUser(): ?Authenticatable
    {
        try {
            if (Auth::getFacadeRoot()) {
                return Auth::user();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
