<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\FuzzyMatcher;

function cmd(string $id, string $label, ?string $description = null): Command
{
    return new Command(
        id: $id,
        label: $label,
        url: '/admin/'.$id,
        description: $description,
    );
}

it('returns 100 for an empty query (everything matches)', function (): void {
    expect(FuzzyMatcher::score('', 'whatever'))->toBe(100);
});

it('returns 95 for a case-insensitive exact match', function (): void {
    expect(FuzzyMatcher::score('Users', 'users'))->toBe(95)
        ->and(FuzzyMatcher::score('USERS', 'users'))->toBe(95);
});

it('returns 80 for a case-insensitive str_contains match', function (): void {
    expect(FuzzyMatcher::score('user', 'List Users'))->toBe(80);
});

it('returns at least 50 for a subsequence match like "usr" in "users"', function (): void {
    $score = FuzzyMatcher::score('usr', 'users');

    expect($score)->toBeGreaterThanOrEqual(50);
});

it('matches "crt ps" inside "Create Post" with a positive score', function (): void {
    expect(FuzzyMatcher::score('crt ps', 'Create Post'))->toBeGreaterThan(0);
});

it('returns 0 when the query letters are not in order in the haystack', function (): void {
    expect(FuzzyMatcher::score('xyz', 'users'))->toBe(0);
});

it('rank() drops zero-scored commands', function (): void {
    $hits = FuzzyMatcher::rank([
        cmd('users', 'Users'),
        cmd('xyz', 'Posts'),
    ], 'user');

    expect($hits)->toHaveCount(1)
        ->and($hits[0]->id)->toBe('users');
});

it('rank() sorts results by descending score', function (): void {
    $exact = cmd('a', 'user');
    $contains = cmd('b', 'List Users');
    $subseq = cmd('c', 'Underwriters');

    $hits = FuzzyMatcher::rank([$contains, $subseq, $exact], 'user');

    expect($hits[0]->id)->toBe('a'); // exact (95)
    expect($hits[1]->id)->toBe('b'); // contains (80)
});

it('rank() respects the limit', function (): void {
    $commands = [];
    for ($i = 0; $i < 50; $i++) {
        $commands[] = cmd("c-{$i}", "Command {$i}");
    }

    expect(FuzzyMatcher::rank($commands, '', 5))->toHaveCount(5);
});

it('rank() also scores command descriptions', function (): void {
    $hits = FuzzyMatcher::rank([
        cmd('a', 'Posts', 'Manage user accounts'),
        cmd('b', 'Tags'),
    ], 'user accounts');

    expect($hits)->toHaveCount(1)
        ->and($hits[0]->id)->toBe('a');
});

it('rank() returns empty when given no commands', function (): void {
    expect(FuzzyMatcher::rank([], 'anything'))->toBe([]);
});

it('rank() preserves original insertion order on score ties', function (): void {
    // All three labels score 100 against an empty query — the order
    // must match the input array exactly.
    $first = cmd('a', 'Alpha');
    $second = cmd('b', 'Bravo');
    $third = cmd('c', 'Charlie');

    $hits = FuzzyMatcher::rank([$first, $second, $third], '');

    expect(array_map(fn (Command $c): string => $c->id, $hits))->toBe(['a', 'b', 'c']);
});

it('rank() returns an empty array when limit is zero', function (): void {
    $hits = FuzzyMatcher::rank([
        cmd('a', 'Alpha'),
        cmd('b', 'Bravo'),
    ], '', 0);

    expect($hits)->toBe([]);
});
