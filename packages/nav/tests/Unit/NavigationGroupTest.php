<?php

declare(strict_types=1);

use Arqel\Nav\NavigationGroup;
use Arqel\Nav\NavigationItem;

it('rejects non-NavigationItem entries when assigning items', function (): void {
    $group = NavigationGroup::make('People')->items([
        NavigationItem::make('Users'),
        'not-an-item',
        NavigationItem::make('Roles'),
    ]);

    expect($group->getItems())->toHaveCount(2);
});

it('addItem appends in order', function (): void {
    $group = NavigationGroup::make('People')
        ->addItem(NavigationItem::make('Users'))
        ->addItem(NavigationItem::make('Roles'));

    expect($group->getItems())->toHaveCount(2)
        ->and($group->getItems()[0]->getLabel())->toBe('Users')
        ->and($group->getItems()[1]->getLabel())->toBe('Roles');
});

it('collapsed() implies collapsible()', function (): void {
    $group = NavigationGroup::make('Advanced')->collapsible(false)->collapsed();

    expect($group->toArray())->toMatchArray([
        'collapsible' => true,
        'collapsed' => true,
    ]);
});

it('toArray sorts items by sort and filters by visibility', function (): void {
    $group = NavigationGroup::make('Mixed')->items([
        NavigationItem::make('Z')->sort(20),
        NavigationItem::make('Hidden')->visible(fn () => false)->sort(5),
        NavigationItem::make('A')->sort(10),
    ]);

    $payload = $group->toArray();

    expect($payload['kind'])->toBe('group')
        ->and($payload['label'])->toBe('Mixed')
        ->and(array_column($payload['items'], 'label'))->toBe(['A', 'Z']);
});
