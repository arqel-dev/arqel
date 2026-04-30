<?php

declare(strict_types=1);

use Arqel\Table\Columns\TextColumn;

it('defaults all three visibility flags to false', function (): void {
    $column = TextColumn::make('email');

    expect($column->isTogglable())->toBeFalse();
    expect($column->isHiddenByDefault())->toBeFalse();
    expect($column->isHiddenOnMobile())->toBeFalse();
});

it('togglable() flips the flag to true and back to false', function (): void {
    $column = TextColumn::make('email')->togglable();

    expect($column->isTogglable())->toBeTrue();

    $column->togglable(false);

    expect($column->isTogglable())->toBeFalse();
});

it('hiddenByDefault() auto-enables togglable', function (): void {
    $column = TextColumn::make('phone')->hiddenByDefault();

    expect($column->isHiddenByDefault())->toBeTrue();
    expect($column->isTogglable())->toBeTrue();
});

it('hiddenByDefault(false) does not flip togglable on', function (): void {
    $column = TextColumn::make('phone')->hiddenByDefault(false);

    expect($column->isHiddenByDefault())->toBeFalse();
    expect($column->isTogglable())->toBeFalse();
});

it('togglable(false) wins after hiddenByDefault() — togglable is the final source of truth', function (): void {
    $column = TextColumn::make('phone')
        ->hiddenByDefault()
        ->togglable(false);

    expect($column->isHiddenByDefault())->toBeTrue();
    expect($column->isTogglable())->toBeFalse();
});

it('hiddenOnMobile() flips independently from togglable/hiddenByDefault', function (): void {
    $column = TextColumn::make('description')->hiddenOnMobile();

    expect($column->isHiddenOnMobile())->toBeTrue();
    expect($column->isTogglable())->toBeFalse();
    expect($column->isHiddenByDefault())->toBeFalse();

    $column->hiddenOnMobile(false);

    expect($column->isHiddenOnMobile())->toBeFalse();
});

it('exposes all three flags in toArray() with default false values', function (): void {
    $array = TextColumn::make('email')->toArray();

    expect($array)->toHaveKeys(['togglable', 'hiddenByDefault', 'hiddenOnMobile']);
    expect($array['togglable'])->toBeFalse();
    expect($array['hiddenByDefault'])->toBeFalse();
    expect($array['hiddenOnMobile'])->toBeFalse();
});

it('exposes all three flags in toArray() when enabled', function (): void {
    $array = TextColumn::make('phone')
        ->hiddenByDefault()
        ->hiddenOnMobile()
        ->toArray();

    expect($array['togglable'])->toBeTrue();
    expect($array['hiddenByDefault'])->toBeTrue();
    expect($array['hiddenOnMobile'])->toBeTrue();
});

it('returns static for fluent chaining on every visibility setter', function (): void {
    $column = TextColumn::make('email');

    expect($column->togglable())->toBe($column);
    expect($column->hiddenByDefault())->toBe($column);
    expect($column->hiddenOnMobile())->toBe($column);
});
