<?php

declare(strict_types=1);

use Arqel\Workflow\Filters\StateFilter;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Support\Facades\Lang;

it('localizes the state filter label at serialization time', function (): void {
    Lang::addLines(['messages.workflow.state_filter_label' => 'State'], 'en', 'arqel');
    Lang::addLines(['messages.workflow.state_filter_label' => 'Estado'], 'pt_BR', 'arqel');

    $filter = StateFilter::make('order_state', WorkflowOrder::class);

    app()->setLocale('en');
    expect($filter->toArray()['label'])->toBe('State');

    app()->setLocale('pt_BR');
    expect($filter->toArray()['label'])->toBe('Estado');
});

it('falls back to the English literal when the key is untranslated', function (): void {
    app()->setLocale('en');

    $filter = StateFilter::make('order_state', WorkflowOrder::class);

    // No translation registered for the namespace key -> stable fallback.
    expect($filter->toArray()['label'])->toBe('State');
});
