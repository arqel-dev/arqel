<?php

declare(strict_types=1);

use Arqel\Table\Table;

it('defaults the mobile mode to stacked', function (): void {
    $table = new Table;

    expect($table->getMobileMode())->toBe(Table::MOBILE_MODE_STACKED);
    expect($table->getMobileMode())->toBe('stacked');
});

it('flips the mobile mode to scroll when configured', function (): void {
    $table = new Table()->mobileMode(Table::MOBILE_MODE_SCROLL);

    expect($table->getMobileMode())->toBe('scroll');
});

it('falls back to stacked defensively for unknown values', function (): void {
    $table = new Table()->mobileMode('grid');

    expect($table->getMobileMode())->toBe(Table::MOBILE_MODE_STACKED);
});

it('also falls back to stacked when an empty string is passed', function (): void {
    $table = new Table()->mobileMode('');

    expect($table->getMobileMode())->toBe(Table::MOBILE_MODE_STACKED);
});

it('exposes the mobile mode in the toArray payload under config', function (): void {
    $stacked = new Table()->toArray();
    $scroll = new Table()->mobileMode('scroll')->toArray();

    expect($stacked)->toHaveKey('config');
    expect($stacked['config'])->toHaveKey('mobileMode', 'stacked');
    expect($scroll['config'])->toHaveKey('mobileMode', 'scroll');
});

it('exposes the two mode constants', function (): void {
    expect(Table::MOBILE_MODE_STACKED)->toBe('stacked');
    expect(Table::MOBILE_MODE_SCROLL)->toBe('scroll');
});

it('returns self from mobileMode for fluent chaining', function (): void {
    $table = new Table;

    expect($table->mobileMode('scroll'))->toBe($table);
});
