<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\FuzzyMatcher;

it('keeps a rankScore command even when its label would fuzzy-score zero', function () {
    // label "Zzz" has no relation to query "ana" → fuzzy score 0 → normally dropped.
    $record = new Command(
        id: 'record:users:1',
        label: 'Zzz',
        url: '/admin/users/1/edit',
        rankScore: 60,
    );
    $nav = new Command(id: 'nav:users', label: 'Users', url: '/admin/users');

    $ranked = FuzzyMatcher::rank([$record, $nav], 'ana');

    $ids = array_map(fn (Command $c): string => $c->id, $ranked);
    expect($ids)->toContain('record:users:1');
});

it('orders exact fuzzy matches above fixed-score records', function () {
    $record = new Command(id: 'record:users:1', label: 'Zzz', url: '/x', rankScore: 60);
    $exact = new Command(id: 'nav:ana', label: 'ana', url: '/y'); // exact → 95

    $ranked = FuzzyMatcher::rank([$record, $exact], 'ana');

    expect($ranked[0]->id)->toBe('nav:ana');
});

it('leaves normal (null rankScore) commands fuzzy-filtered as before', function () {
    $miss = new Command(id: 'nav:zzz', label: 'Zzz', url: '/z'); // no match for "ana"
    $hit = new Command(id: 'nav:ana', label: 'ana', url: '/a');

    $ranked = FuzzyMatcher::rank([$miss, $hit], 'ana');

    $ids = array_map(fn (Command $c): string => $c->id, $ranked);
    expect($ids)->toBe(['nav:ana']); // miss dropped, unchanged behavior
});
