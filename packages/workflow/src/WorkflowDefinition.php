<?php

declare(strict_types=1);

namespace Arqel\Workflow;

use InvalidArgumentException;

/**
 * Fluent builder describing the workflow attached to an Eloquent model.
 *
 * The definition is *metadata only* — it does not own the persisted state
 * or trigger transitions. It only enumerates the state classes (or
 * tokens), their UI metadata (`label`, `color`, `icon`) and the list of
 * transition classes that operate on them. The trait `HasWorkflow`
 * consumes the definition to expose helpers to controllers and React.
 *
 * Designed to be **duck-typed** with `spatie/laravel-model-states`:
 * - state keys can be `class-string<State>` from spatie OR any string
 *   token (PHP enum value, custom slug, ...) — we never call methods on
 *   them, only compare values
 * - transition entries are `class-string` strings; the only optional
 *   contract is a static `from(): array<class-string|string>` method
 *   used by `getAvailableTransitions()` to filter eligible transitions
 *   for the current state
 */
final class WorkflowDefinition
{
    /**
     * @var array<string, array{label: string, color: string, icon: string}>
     */
    private array $states = [];

    /**
     * @var list<class-string>
     */
    private array $transitions = [];

    private function __construct(private readonly string $field) {}

    /**
     * Start a definition for the given Eloquent attribute / cast field.
     */
    public static function make(string $field): self
    {
        if (trim($field) === '') {
            throw new InvalidArgumentException('WorkflowDefinition field cannot be empty.');
        }

        return new self($field);
    }

    /**
     * Register the state map.
     *
     * @param  array<string, array{label?: string, color?: string, icon?: string}>  $states
     *                                                                                       Keys are state identifiers — typically `class-string<State>` from
     *                                                                                       spatie/laravel-model-states, but any non-empty string works.
     */
    public function states(array $states): self
    {
        $normalised = [];

        foreach ($states as $key => $meta) {
            if (! is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException(
                    'WorkflowDefinition::states() expects string keys (state class-strings or tokens).',
                );
            }

            $label = isset($meta['label']) && is_string($meta['label']) && $meta['label'] !== ''
                ? $meta['label']
                : self::guessLabel($key);

            $color = isset($meta['color']) && is_string($meta['color']) && $meta['color'] !== ''
                ? $meta['color']
                : 'secondary';

            $icon = isset($meta['icon']) && is_string($meta['icon']) && $meta['icon'] !== ''
                ? $meta['icon']
                : 'circle';

            $normalised[$key] = [
                'label' => $label,
                'color' => $color,
                'icon' => $icon,
            ];
        }

        $this->states = $normalised;

        return $this;
    }

    /**
     * Register the transition class list.
     *
     * @param  list<class-string>  $transitions
     */
    public function transitions(array $transitions): self
    {
        $list = [];

        foreach ($transitions as $transition) {
            if (! is_string($transition) || $transition === '') {
                throw new InvalidArgumentException(
                    'WorkflowDefinition::transitions() expects a list of class-strings.',
                );
            }

            $list[] = $transition;
        }

        $this->transitions = $list;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * @return list<class-string>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * Lookup metadata for a single state class / token.
     *
     * @return array{label: string, color: string, icon: string}|null
     */
    public function getStateMetadata(string $stateClass): ?array
    {
        return $this->states[$stateClass] ?? null;
    }

    /**
     * Serialize the definition for React consumption.
     *
     * @return array{
     *     field: string,
     *     states: array<string, array{label: string, color: string, icon: string}>,
     *     transitions: list<class-string>,
     * }
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'states' => $this->states,
            'transitions' => $this->transitions,
        ];
    }

    /**
     * Derive a human-readable label from a state class-string when the
     * caller does not supply one. `App\Order\States\PendingPayment` →
     * `Pending Payment`.
     */
    private static function guessLabel(string $stateKey): string
    {
        $basename = str_contains($stateKey, '\\')
            ? (string) substr($stateKey, (int) strrpos($stateKey, '\\') + 1)
            : $stateKey;

        $spaced = (string) preg_replace('/(?<!^)(?=[A-Z])/', ' ', $basename);

        return trim($spaced);
    }
}
