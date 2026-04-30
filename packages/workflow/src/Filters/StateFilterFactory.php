<?php

declare(strict_types=1);

namespace Arqel\Workflow\Filters;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Helper que constrói um {@see StateFilter} a partir apenas do model class,
 * resolvendo o `field` automaticamente via `arqelWorkflow()->getField()`.
 *
 * Útil em user-land:
 *
 * ```php
 * StateFilterFactory::forResource(Order::class);
 * // equivalente a StateFilter::make('order_state', Order::class)
 * ```
 */
final class StateFilterFactory
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  string|null  $field  Override opcional; se omitido, lê do `WorkflowDefinition`.
     */
    public static function forResource(string $modelClass, ?string $field = null): StateFilter
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException(
                "StateFilterFactory modelClass [{$modelClass}] does not exist.",
            );
        }

        $traits = class_uses_recursive($modelClass);

        if (! in_array(HasWorkflow::class, $traits, true)) {
            throw new InvalidArgumentException(
                "StateFilterFactory modelClass [{$modelClass}] must use the HasWorkflow trait.",
            );
        }

        $resolvedField = $field;

        if ($resolvedField === null || trim($resolvedField) === '') {
            /** @var Model $instance */
            $instance = new $modelClass;

            /** @var WorkflowDefinition $definition */
            $definition = $instance->arqelWorkflow(); // @phpstan-ignore method.notFound

            $resolvedField = $definition->getField();
        }

        return StateFilter::make($resolvedField, $modelClass);
    }
}
