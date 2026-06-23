<?php

declare(strict_types=1);

use Arqel\Workflow\Fields\StateTransitionField;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Support\Facades\Lang;

/**
 * Prime a flat (JSON-style) string translation so that `trans('Pending To
 * Paid')` resolves in the given locale. Group-based `addLines()` cannot host a
 * key with spaces/no dot, which is exactly the shape of a derived label.
 */
function primeFlatLine(string $line, string $translation, string $locale): void
{
    $dir = sys_get_temp_dir().'/arqel-wf-i18n-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir."/{$locale}.json", json_encode([$line => $translation]));
    Lang::addJsonPath($dir);
}

it('localises the derived transition label when a translation exists', function (): void {
    // The framework derives the transition label from the class short-name
    // (`PendingToPaid` -> `Pending To Paid`) and routes it through trans() at
    // serialization time, so a registered translation overrides it per locale.
    primeFlatLine('Pending To Paid', 'Marcar como pago', 'pt_BR');

    app()->setLocale('pt_BR');

    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $field = StateTransitionField::make('state')->record($order);

    $transitions = $field->resolveAvailableTransitions();
    $labels = array_column($transitions, 'label');

    expect($labels)->toContain('Marcar como pago');
});

it('passes a literal transition label through untouched', function (): void {
    app()->setLocale('pt_BR');

    $order = new WorkflowOrder;
    $order->order_state = PendingState::class;

    $field = StateTransitionField::make('state')->record($order);

    $labels = array_column($field->resolveAvailableTransitions(), 'label');

    // No translation registered for the derived label: it passes through as the
    // English literal the framework computed from the transition class name.
    expect($labels)->toContain('Pending To Paid');
});

it('localises the current-state label through the active locale', function (): void {
    Lang::addLines([
        'workflow.states.pending' => 'Aguardando',
    ], 'pt_BR', 'arqel');

    app()->setLocale('pt_BR');

    $order = new WorkflowOrder;
    $order->setRawAttributes(['order_state' => 'arqel::workflow.states.pending']);

    $field = StateTransitionField::make('state')->record($order);

    $current = $field->resolveCurrentState();

    // Bare-key fallback (no metadata for this token) must localize the key.
    expect($current)->not->toBeNull()
        ->and($current['label'])->toBe('Aguardando');
});
