<?php

declare(strict_types=1);

use Arqel\Workflow\WorkflowServiceProvider;

it('boots the workflow service provider', function (): void {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(WorkflowServiceProvider::class)
        ->and($providers[WorkflowServiceProvider::class])->toBeTrue();
});

it('exposes the arqel-workflow config with default fallbacks', function (): void {
    expect(config('arqel-workflow'))->toBeArray()
        ->and(config('arqel-workflow.default_color'))->toBe('secondary')
        ->and(config('arqel-workflow.default_icon'))->toBe('circle');
});
