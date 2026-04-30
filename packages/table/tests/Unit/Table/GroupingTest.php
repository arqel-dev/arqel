<?php

declare(strict_types=1);

use Arqel\Table\Summaries\Summary;
use Arqel\Table\Table;
use Illuminate\Support\Collection;

function groupingTestRecords(): Collection
{
    return new Collection([
        (object) ['id' => 1, 'status' => 'active', 'amount' => 100],
        (object) ['id' => 2, 'status' => 'active', 'amount' => 200],
        (object) ['id' => 3, 'status' => 'archived', 'amount' => 50],
        (object) ['id' => 4, 'status' => 'active', 'amount' => 75],
    ]);
}

it('returns a single "All" group when no groupBy is set', function (): void {
    $table = new Table()->groupSummaries([Summary::sum('amount')]);
    $groups = $table->buildGroups(groupingTestRecords());

    expect($groups)->toHaveCount(1);
    expect($groups[0]['key'])->toBeNull();
    expect($groups[0]['records'])->toHaveCount(4);
    expect($groups[0]['summaries'][0]['value'])->toBe(425);
});

it('groups records by the configured field', function (): void {
    $table = new Table()->groupBy('status');
    $groups = $table->buildGroups(groupingTestRecords());

    expect($groups)->toHaveCount(2);

    $statuses = array_map(static fn (array $g): string => (string) $g['key'], $groups);
    expect($statuses)->toContain('active');
    expect($statuses)->toContain('archived');
});

it('applies the labelResolver when provided', function (): void {
    // Resolver receives the first record of each group, not the key
    $table = new Table()
        ->groupBy('status', static fn (mixed $record): string => 'Status: '.ucfirst((string) $record->status));

    $groups = $table->buildGroups(groupingTestRecords());
    $labels = array_map(static fn (array $g): string => $g['label'], $groups);

    expect($labels)->toContain('Status: Active');
    expect($labels)->toContain('Status: Archived');
});

it('computes summaries per group', function (): void {
    $table = new Table()
        ->groupBy('status')
        ->groupSummaries([
            Summary::sum('amount')->label('Total'),
            Summary::count()->label('Count'),
        ]);

    $groups = $table->buildGroups(groupingTestRecords());
    $active = collect($groups)->firstWhere('key', 'active');

    expect($active['summaries'])->toHaveCount(2);
    $sum = collect($active['summaries'])->firstWhere('type', 'sum');
    expect($sum['value'])->toBe(375);
    $count = collect($active['summaries'])->firstWhere('type', 'count');
    expect($count['value'])->toBe(3);
});

it('silently filters non-Summary entries from groupSummaries()', function (): void {
    $table = new Table()
        ->groupSummaries([Summary::sum('amount'), 'junk', new stdClass, 42]);

    $groups = $table->buildGroups(groupingTestRecords());

    expect($groups[0]['summaries'])->toHaveCount(1);
});

it('exposes groupBy + summaries in toArray()', function (): void {
    $table = new Table()
        ->groupBy('status')
        ->groupSummaries([Summary::sum('amount')]);

    $payload = $table->toArray();

    expect($payload['groupBy'])->toBe('status');
    expect($payload['summaries'])->toHaveCount(1);
    expect($payload['summaries'][0]['type'])->toBe('sum');
});
