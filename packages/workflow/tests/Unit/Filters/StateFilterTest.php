<?php

declare(strict_types=1);

use Arqel\Workflow\Filters\StateFilter;
use Arqel\Workflow\Filters\StateFilterFactory;
use Arqel\Workflow\Tests\Fixtures\CancelledState;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Database\Eloquent\Model;

it('makes a StateFilter via factory method', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);

    expect($filter)->toBeInstanceOf(StateFilter::class)
        ->and($filter->field)->toBe('order_state')
        ->and($filter->modelClass)->toBe(WorkflowOrder::class);
});

it('serializes to a state-typed array shape with label and options', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $array = $filter->toArray();

    expect($array)->toHaveKeys(['field', 'type', 'label', 'options'])
        ->and($array['field'])->toBe('order_state')
        ->and($array['type'])->toBe('state')
        ->and($array['label'])->toBe('State')
        ->and($array['options'])->toBeArray()
        ->and($array['options'])->toHaveCount(4);
});

it('exposes optionsArray with value/label/color/icon per registered state', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $options = $filter->optionsArray();

    expect($options)->toHaveKey(PendingState::class)
        ->and($options[PendingState::class])->toBe([
            'value' => PendingState::class,
            'label' => 'Pending',
            'color' => 'warning',
            'icon' => 'clock',
        ])
        ->and($options[PaidState::class]['label'])->toBe('Paid')
        ->and($options[CancelledState::class]['icon'])->toBe('x-circle');
});

it('applies a where clause for a single string value', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $query = WorkflowOrder::query();

    $filter->apply($query, PaidState::class);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($sql)->toContain('"order_state" = ?')
        ->and($bindings)->toBe([PaidState::class]);
});

it('applies a whereIn clause for an array value (multi-select)', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $query = WorkflowOrder::query();

    $filter->apply($query, [PendingState::class, PaidState::class]);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($sql)->toContain('"order_state" in (?, ?)')
        ->and($bindings)->toBe([PendingState::class, PaidState::class]);
});

it('is a no-op when value is null, empty string, or empty array', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);

    foreach ([null, '', []] as $value) {
        $query = WorkflowOrder::query();
        $filter->apply($query, $value);

        expect($query->getQuery()->wheres)->toBe([]);
    }
});

it('throws when the model class does not use HasWorkflow', function (): void {
    $bareModel = new class extends Model
    {
        protected $table = 'noop';
    };

    StateFilter::make('state', $bareModel::class);
})->throws(InvalidArgumentException::class);

it('throws when the model class does not exist', function (): void {
    /** @phpstan-ignore-next-line argument.type */
    StateFilter::make('state', 'App\\Does\\Not\\Exist');
})->throws(InvalidArgumentException::class);

it('throws when field is empty', function (): void {
    StateFilter::make('   ', WorkflowOrder::class);
})->throws(InvalidArgumentException::class);

it('resolves field automatically via StateFilterFactory::forResource', function (): void {
    $filter = StateFilterFactory::forResource(WorkflowOrder::class);

    expect($filter->field)->toBe('order_state')
        ->and($filter->modelClass)->toBe(WorkflowOrder::class);
});

it('respects an explicit field override in StateFilterFactory::forResource', function (): void {
    $filter = StateFilterFactory::forResource(WorkflowOrder::class, 'custom_state_column');

    expect($filter->field)->toBe('custom_state_column');
});

it('rejects models without HasWorkflow in StateFilterFactory::forResource', function (): void {
    $bareModel = new class extends Model
    {
        protected $table = 'noop';
    };

    StateFilterFactory::forResource($bareModel::class);
})->throws(InvalidArgumentException::class);
