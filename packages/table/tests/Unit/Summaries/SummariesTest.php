<?php

declare(strict_types=1);

use Arqel\Table\Summaries\Summary;
use Illuminate\Support\Collection;

function summariesTestRecords(): Collection
{
    return new Collection([
        ['id' => 1, 'amount' => 10],
        ['id' => 2, 'amount' => 20],
        ['id' => 3, 'amount' => 30],
        ['id' => 4, 'amount' => null],
    ]);
}

it('Summary::sum computes the total over the field', function (): void {
    $summary = Summary::sum('amount');
    expect($summary->compute(summariesTestRecords()))->toBe(60);
    expect($summary->toArray()['type'])->toBe('sum');
});

it('Summary::avg computes the average over the field (Collection.avg() skips nulls)', function (): void {
    $summary = Summary::avg('amount');
    // 60 / 3 non-null records = 20 (Collection's avg behaviour)
    expect($summary->compute(summariesTestRecords()))->toEqual(20);
});

it('Summary::avg returns 0 on empty collection', function (): void {
    expect(Summary::avg('amount')->compute(new Collection([])))->toEqual(0);
});

it('Summary::count(null) counts all records', function (): void {
    expect(Summary::count()->compute(summariesTestRecords()))->toBe(4);
});

it('Summary::count(field) counts non-null occurrences', function (): void {
    $count = Summary::count('amount')->compute(summariesTestRecords());
    expect($count)->toBe(3);
});

it('Summary::min returns the smallest value', function (): void {
    expect(Summary::min('amount')->compute(summariesTestRecords()))->toBe(10);
});

it('Summary::max returns the largest value', function (): void {
    expect(Summary::max('amount')->compute(summariesTestRecords()))->toBe(30);
});

it('Summary::label() overrides the default label', function (): void {
    $summary = Summary::sum('amount')->label('Receita Total');
    expect($summary->toArray()['label'])->toBe('Receita Total');
});

it('emits {type, field, label} in toArray()', function (): void {
    $payload = Summary::sum('amount')->toArray();
    expect($payload)->toHaveKeys(['type', 'field', 'label']);
    expect($payload['type'])->toBe('sum');
    expect($payload['field'])->toBe('amount');
});
