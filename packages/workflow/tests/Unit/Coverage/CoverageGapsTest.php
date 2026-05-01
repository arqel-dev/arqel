<?php

declare(strict_types=1);

use Arqel\Workflow\Authorization\TransitionAuthorizer;
use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Filters\StateFilter;
use Arqel\Workflow\Models\StateTransition;
use Arqel\Workflow\Tests\Fixtures\AnyToCancelled;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PaidToShipped;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\PendingToPaid;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Arqel\Workflow\WorkflowDefinition;

/**
 * Cobertura de gaps observados em WF-009. Cada teste documenta a área não
 * exercitada antes — útil para futuras revisões de regressão.
 */
it('guesses labels for class-strings without namespace and PascalCase suffix', function (): void {
    // Caso "no namespace" — atinge o ramo `else` de guessLabel.
    $def = WorkflowDefinition::make('state')
        ->states(['Approved' => []]);

    expect($def->getStateMetadata('Approved'))->toBe([
        'label' => 'Approved',
        'color' => 'secondary',
        'icon' => 'circle',
    ]);

    // Caso "single word with State suffix and digits" — guessLabel não
    // remove "State" (esse strip é só do TransitionAuthorizer::slugifyState).
    $def2 = WorkflowDefinition::make('state')
        ->states(['Step1State' => []]);

    $meta = $def2->getStateMetadata('Step1State') ?? [];
    expect($meta)->toHaveKey('label')
        ->and($meta['label'] ?? null)->toBe('Step1 State');
});

it('preserves transition declaration order in WorkflowDefinition::getTransitions', function (): void {
    $def = WorkflowDefinition::make('state')
        ->transitions([AnyToCancelled::class, PaidToShipped::class, PendingToPaid::class]);

    expect($def->getTransitions())->toBe([
        AnyToCancelled::class,
        PaidToShipped::class,
        PendingToPaid::class,
    ]);
});

it('lets a second states() call replace the previous map (not merge)', function (): void {
    $def = WorkflowDefinition::make('state')
        ->states([PendingState::class => ['label' => 'A']])
        ->states([PaidState::class => ['label' => 'B']]);

    expect($def->getStates())->toHaveCount(1)
        ->and($def->getStateMetadata(PendingState::class))->toBeNull()
        ->and($def->getStateMetadata(PaidState::class))->not->toBeNull();
});

it('slugifies edge cases: bare strings, digits, empty, wildcard', function (): void {
    expect(TransitionAuthorizer::slugifyState(''))->toBe('*')
        ->and(TransitionAuthorizer::slugifyState('*'))->toBe('*')
        ->and(TransitionAuthorizer::slugifyState('pending'))->toBe('pending')
        ->and(TransitionAuthorizer::slugifyState('Step1State'))->toBe('step1')
        // FQCN sem suffix State preserva nome curto.
        ->and(TransitionAuthorizer::slugifyState('App\\States\\Approved'))->toBe('approved');
});

it('filters non-string values from a multi-select StateFilter::apply array', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $query = WorkflowOrder::query();

    // Mistura int/null/string vazia + valores válidos. O filtro deve manter
    // apenas as strings não-vazias.
    /** @var array<int, mixed> $value */
    $value = [PendingState::class, 0, null, '', PaidState::class];
    $filter->apply($query, $value);

    expect($query->getBindings())->toBe([PendingState::class, PaidState::class]);
});

it('falls back to defaults in StateFilter::optionsArray when state metadata is partial', function (): void {
    $filter = StateFilter::make('order_state', WorkflowOrder::class);
    $options = $filter->optionsArray();

    // Todos states do fixture têm color+icon definidos; este teste apenas
    // garante que `optionsArray()` nunca retorna `null` em color/icon —
    // os fallbacks são aplicados por `WorkflowDefinition::states()`.
    foreach ($options as $option) {
        expect($option['color'])->toBeString()
            ->and($option['color'])->not->toBe('')
            ->and($option['icon'])->toBeString()
            ->and($option['icon'])->not->toBe('');
    }
});

it('round-trips metadata array cast on StateTransition model', function (): void {
    $entry = StateTransition::create([
        'model_type' => WorkflowOrder::class,
        'model_id' => 1,
        'from_state' => PendingState::class,
        'to_state' => PaidState::class,
        'transitioned_by_user_id' => null,
        'metadata' => ['source' => 'webhook', 'attempts' => 3],
    ]);

    /** @var StateTransition $reloaded */
    $reloaded = StateTransition::query()->findOrFail($entry->id);

    expect($reloaded->metadata)->toBe(['source' => 'webhook', 'attempts' => 3]);
});

it('resolves the model() MorphTo relationship to the originating record', function (): void {
    $order = WorkflowOrder::create(['order_state' => PendingState::class]);

    $entry = StateTransition::create([
        'model_type' => WorkflowOrder::class,
        'model_id' => $order->getKey(),
        'from_state' => PendingState::class,
        'to_state' => PaidState::class,
        'transitioned_by_user_id' => null,
        'metadata' => null,
    ]);

    /** @var WorkflowOrder|null $related */
    $related = $entry->model()->first();

    expect($related)->not->toBeNull()
        ->and($related?->getKey())->toBe($order->getKey());
});

it('preserves transition declaration order in StateTransitionField::resolveAvailableTransitions', function (): void {
    $order = new WorkflowOrder(['order_state' => PendingState::class]);
    $field = StateTransitionField::make('state')->record($order);

    $transitions = $field->resolveAvailableTransitions();

    // PendingToPaid declarada antes de AnyToCancelled em WorkflowOrder.
    $tos = array_map(static fn (array $t): string => $t['to'], $transitions);

    // Encontra os índices manualmente para manter PHPStan strict (array_search
    // retorna `int|false` que não é `int|string` aceito por toBeLessThan).
    $pendingIdx = -1;
    $cancelIdx = -1;
    foreach ($tos as $idx => $to) {
        if ($to === 'PendingToPaid' && $pendingIdx === -1) {
            $pendingIdx = $idx;
        }
        if ($to === 'AnyToCancelled' && $cancelIdx === -1) {
            $cancelIdx = $idx;
        }
    }

    expect($pendingIdx)->toBeGreaterThanOrEqual(0)
        ->and($cancelIdx)->toBeGreaterThanOrEqual(0)
        ->and($pendingIdx)->toBeLessThan($cancelIdx);
});
