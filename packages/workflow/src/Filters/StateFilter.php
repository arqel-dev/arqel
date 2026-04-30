<?php

declare(strict_types=1);

namespace Arqel\Workflow\Filters;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Filtro standalone que extrai automaticamente as options a partir do
 * {@see WorkflowDefinition} de um Eloquent model que usa o trait
 * {@see HasWorkflow}. É **agnóstico** do `arqel/table`: expõe um
 * `toArray()` shape genérico que o user-land pluga em
 * `Table::filters([...])` quando o pacote `arqel/table` está disponível.
 *
 * Mantém `arqel/workflow` standalone — sem dep em `arqel/table`.
 *
 * @phpstan-type StateOption array{value: string, label: string, color: string, icon: string}
 */
final readonly class StateFilter
{
    /**
     * @param string $field Coluna Eloquent que persiste a state (e.g. `order_state`).
     * @param class-string<Model> $modelClass Eloquent model que usa o trait `HasWorkflow`.
     */
    public function __construct(
        public string $field,
        public string $modelClass,
    ) {
        if (trim($field) === '') {
            throw new InvalidArgumentException('StateFilter field cannot be empty.');
        }

        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException(
                "StateFilter modelClass [{$modelClass}] does not exist.",
            );
        }

        $traits = class_uses_recursive($modelClass);

        if (! in_array(HasWorkflow::class, $traits, true)) {
            throw new InvalidArgumentException(
                "StateFilter modelClass [{$modelClass}] must use the HasWorkflow trait.",
            );
        }
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public static function make(string $field, string $modelClass): self
    {
        return new self($field, $modelClass);
    }

    /**
     * Serialização para Inertia / consumo do `arqel/table`.
     *
     * @return array{
     *     field: string,
     *     type: string,
     *     label: string,
     *     options: array<int, StateOption>,
     * }
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => 'state',
            'label' => 'State',
            'options' => array_values($this->optionsArray()),
        ];
    }

    /**
     * Lista de options derivada de `WorkflowDefinition::getStates()`.
     *
     * @return array<string, StateOption>
     */
    public function optionsArray(): array
    {
        $definition = $this->resolveDefinition();
        $options = [];

        foreach ($definition->getStates() as $key => $meta) {
            $options[$key] = [
                'value' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'icon' => $meta['icon'],
            ];
        }

        return $options;
    }

    /**
     * Aplica o filtro a uma query Eloquent / Builder. No-op silencioso quando
     * `$value` é null, string vazia ou array vazio. Multi-select (array)
     * gera `whereIn`.
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            $valid = array_values(array_filter(
                $value,
                static fn (mixed $v): bool => is_string($v) && $v !== '',
            ));

            if ($valid === []) {
                return;
            }

            $query->whereIn($this->field, $valid);

            return;
        }

        if (! is_string($value) || $value === '') {
            return;
        }

        $query->where($this->field, $value);
    }

    private function resolveDefinition(): WorkflowDefinition
    {
        /** @var Model $instance */
        $instance = new $this->modelClass;

        // The constructor guarantees the HasWorkflow trait is present, so
        // `arqelWorkflow()` exists on the instance — phpstan cannot see
        // through the dynamic class-string of the Eloquent base type.
        /** @var WorkflowDefinition $definition */
        $definition = $instance->arqelWorkflow(); // @phpstan-ignore method.notFound

        return $definition;
    }
}
