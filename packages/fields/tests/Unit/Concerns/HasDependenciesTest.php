<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;

it('starts with no dependencies declared', function (): void {
    $field = new StubField('state');

    expect($field->hasDependencies())->toBeFalse()
        ->and($field->getDependencies())->toBe([]);
});

it('records single and array dependencies and dedupes them', function (): void {
    $field = (new StubField('state'))
        ->dependsOn('country_id')
        ->dependsOn(['country_id', 'region_id']);

    expect($field->hasDependencies())->toBeTrue()
        ->and($field->getDependencies())->toBe(['country_id', 'region_id']);
});

it('returns an empty list when no resolver is registered', function (): void {
    $field = (new StubField('state'))->dependsOn('country_id');

    expect($field->handleDependencyUpdate(['country_id' => 1], 'country_id'))->toBe([]);
});

it('invokes the resolver and returns the new options array', function (): void {
    $field = (new StubField('state'))
        ->dependsOn('country_id')
        ->resolveOptionsUsing(fn (array $state) => $state['country_id'] === 1
            ? [10 => 'Lisbon', 11 => 'Porto']
            : [20 => 'Madrid', 21 => 'Barcelona']);

    expect($field->handleDependencyUpdate(['country_id' => 1], 'country_id'))
        ->toBe([10 => 'Lisbon', 11 => 'Porto'])
        ->and($field->handleDependencyUpdate(['country_id' => 2], 'country_id'))
        ->toBe([20 => 'Madrid', 21 => 'Barcelona']);
});

it('skips the resolver when the changed field is not a dependency', function (): void {
    $invocations = 0;
    $field = (new StubField('state'))
        ->dependsOn('country_id')
        ->resolveOptionsUsing(function () use (&$invocations) {
            $invocations++;

            return [1 => 'Yes'];
        });

    $result = $field->handleDependencyUpdate(['country_id' => 1, 'name' => 'Alice'], 'name');

    expect($result)->toBe([])
        ->and($invocations)->toBe(0);
});

it('coerces non-array resolver returns to an empty array', function (): void {
    $field = (new StubField('state'))
        ->dependsOn('country_id')
        ->resolveOptionsUsing(fn () => 'oops');

    expect($field->handleDependencyUpdate(['country_id' => 1], 'country_id'))->toBe([]);
});

it('exposes the registered resolver via the getter', function (): void {
    $callback = fn () => [1 => 'A'];
    $field = (new StubField('state'))->resolveOptionsUsing($callback);

    expect($field->getResolveOptionsCallback())->toBe($callback);
});

it('invokes the resolver even with no dependencies declared (manual refresh)', function (): void {
    $field = (new StubField('state'))
        ->resolveOptionsUsing(fn () => [1 => 'A', 2 => 'B']);

    expect($field->handleDependencyUpdate([], 'anything'))->toBe([1 => 'A', 2 => 'B']);
});
