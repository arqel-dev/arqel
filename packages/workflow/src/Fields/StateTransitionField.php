<?php

declare(strict_types=1);

namespace Arqel\Workflow\Fields;

use Arqel\Fields\Field;
use Arqel\Workflow\Authorization\TransitionAuthorizer;
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Throwable;

/**
 * Field exibindo o state atual de um model com workflow + botões para
 * as transitions disponíveis.
 *
 * Serializa para o React:
 * - `currentState`: shape `{name, label, color, icon}` ou `null` quando
 *   o record não tem estado.
 * - `transitions`: lista de `{from, to, label, authorized}` derivada
 *   da `WorkflowDefinition` exposta pelo trait `HasWorkflow`.
 * - `history`: placeholder vazio até WF-007 implementar audit log.
 *
 * O componente React `arqel/workflow/StateTransition` (slice C29) consome
 * estes props. Este field é PHP-only por enquanto — a UI ainda não
 * existe.
 *
 * Uso típico:
 *
 * ```php
 * StateTransitionField::make('state')
 *     ->showDescription()
 *     ->showHistory()
 *     ->record($order);
 * ```
 */
final class StateTransitionField extends Field
{
    protected string $type = 'state-transition';

    protected string $component = 'arqel/workflow/StateTransition';

    protected bool $showDescription = false;

    protected bool $showHistory = false;

    protected string $transitionsAttribute = 'state';

    protected ?Model $record = null;

    /**
     * Factory canônica do field.
     */
    public static function make(string $name): static
    {
        return new self($name);
    }

    public function showDescription(bool $show = true): static
    {
        $this->showDescription = $show;

        return $this;
    }

    public function showHistory(bool $show = true): static
    {
        $this->showHistory = $show;

        return $this;
    }

    public function transitionsAttribute(string $name = 'state'): static
    {
        $this->transitionsAttribute = $name;

        return $this;
    }

    public function record(?Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): ?Model
    {
        return $this->record;
    }

    public function getTransitionsAttribute(): string
    {
        return $this->transitionsAttribute;
    }

    public function isShowingDescription(): bool
    {
        return $this->showDescription;
    }

    public function isShowingHistory(): bool
    {
        return $this->showHistory;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'showDescription' => $this->showDescription,
            'showHistory' => $this->showHistory,
            'transitionsAttribute' => $this->transitionsAttribute,
            'currentState' => $this->resolveCurrentState(),
            'transitions' => $this->resolveAvailableTransitions(),
            'history' => $this->resolveHistory(),
        ];
    }

    /**
     * @return array{name: string, label: string, color: ?string, icon: ?string}|null
     */
    public function resolveCurrentState(): ?array
    {
        $definition = $this->resolveDefinition();

        if ($definition === null || $this->record === null) {
            return null;
        }

        $field = $definition->getField();
        /** @var mixed $value */
        $value = $this->record->{$field} ?? null;

        $key = self::stateKey($value);

        if ($key === null) {
            return null;
        }

        $meta = $definition->getStateMetadata($key);

        if ($meta === null) {
            return [
                'name' => $key,
                'label' => $key,
                'color' => null,
                'icon' => null,
            ];
        }

        return [
            'name' => $key,
            'label' => $meta['label'],
            'color' => $meta['color'] ?? null,
            'icon' => $meta['icon'] ?? null,
        ];
    }

    /**
     * @return list<array{from: string, to: string, label: string, authorized: bool}>
     */
    public function resolveAvailableTransitions(): array
    {
        $definition = $this->resolveDefinition();

        if ($definition === null) {
            return [];
        }

        $current = null;

        if ($this->record !== null) {
            /** @var mixed $value */
            $value = $this->record->{$definition->getField()} ?? null;
            $current = self::stateKey($value);
        }

        $entries = [];

        foreach ($definition->getTransitions() as $transitionClass) {
            if (! class_exists($transitionClass)) {
                continue;
            }

            $froms = self::transitionFroms($transitionClass);
            $to = self::transitionTo($transitionClass);
            $label = self::transitionLabel($transitionClass);

            $eligibleFroms = $froms ?? [$current ?? '*'];

            foreach ($eligibleFroms as $from) {
                if ($current !== null && $froms !== null && ! in_array($current, $froms, true)) {
                    continue;
                }

                $entries[] = [
                    'from' => $from,
                    'to' => $to,
                    'label' => $label,
                    'authorized' => $this->isAuthorized($transitionClass),
                ];
            }
        }

        return $entries;
    }

    /**
     * History resolution will be implemented in WF-007.
     *
     * @return list<array<string, mixed>>
     */
    public function resolveHistory(): array
    {
        return [];
    }

    private function resolveDefinition(): ?WorkflowDefinition
    {
        if ($this->record === null) {
            return null;
        }

        if (! in_array(HasWorkflow::class, self::classUsesRecursive($this->record), true)
            && ! method_exists($this->record, 'arqelWorkflow')) {
            return null;
        }

        if (! method_exists($this->record, 'arqelWorkflow')) {
            return null;
        }

        try {
            /** @var WorkflowDefinition $definition */
            $definition = $this->record->arqelWorkflow();

            return $definition;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param class-string $transitionClass
     */
    private function isAuthorized(string $transitionClass): bool
    {
        try {
            $user = null;

            try {
                if (Auth::getFacadeRoot()) {
                    $user = Auth::user();
                }
            } catch (Throwable) {
                $user = null;
            }

            return TransitionAuthorizer::authorize($transitionClass, $user, $this->record);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param class-string $transition
     *
     * @return list<string>|null
     */
    private static function transitionFroms(string $transition): ?array
    {
        if (! method_exists($transition, 'from')) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($transition, 'from');

            if (! $reflection->isStatic() || ! $reflection->isPublic()) {
                return null;
            }

            /** @var mixed $result */
            $result = $reflection->invoke(null);

            if (! is_array($result)) {
                return null;
            }

            $list = [];

            foreach ($result as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    $list[] = $candidate;
                }
            }

            return $list;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param class-string $transition
     */
    private static function transitionTo(string $transition): string
    {
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
                // fall through to label-based default
            }
        }

        return self::shortName($transition);
    }

    /**
     * @param class-string $transition
     */
    private static function transitionLabel(string $transition): string
    {
        $short = self::shortName($transition);

        $spaced = (string) preg_replace('/(?<!^)(?=[A-Z])/', ' ', $short);

        return trim($spaced);
    }

    private static function shortName(string $value): string
    {
        if (! str_contains($value, '\\')) {
            return $value;
        }

        $pos = strrpos($value, '\\');

        return $pos === false ? $value : substr($value, $pos + 1);
    }

    private static function stateKey(mixed $value): ?string
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
     * @return list<class-string>
     */
    private static function classUsesRecursive(object $object): array
    {
        $traits = [];
        $class = $object::class;

        do {
            foreach (class_uses($class) ?: [] as $trait) {
                $traits[] = $trait;
            }
            $class = get_parent_class($class);
        } while ($class !== false);

        return array_values(array_unique($traits));
    }
}
