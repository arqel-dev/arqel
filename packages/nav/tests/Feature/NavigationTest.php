<?php

declare(strict_types=1);

use Arqel\Nav\Navigation;
use Arqel\Nav\NavigationGroup;
use Arqel\Nav\NavigationItem;
use Arqel\Nav\Tests\Fixtures\DashboardResource;
use Arqel\Nav\Tests\Fixtures\UserResource;

it('builds a flat list of items in sort order', function (): void {
    $nav = (new Navigation)
        ->item(NavigationItem::make('B')->sort(20))
        ->item(NavigationItem::make('A')->sort(10));

    $payload = $nav->build();

    expect(array_column($payload, 'label'))->toBe(['A', 'B']);
});

it('groups via the group() callback', function (): void {
    $nav = (new Navigation)
        ->group('People', function (NavigationGroup $group): void {
            $group->items([
                NavigationItem::make('Users'),
                NavigationItem::make('Roles'),
            ]);
        });

    $payload = $nav->build();

    expect($payload[0]['kind'])->toBe('group')
        ->and($payload[0]['label'])->toBe('People')
        ->and($payload[0]['items'])->toHaveCount(2);
});

it('inserts dividers verbatim', function (): void {
    $nav = (new Navigation)
        ->item(NavigationItem::make('A'))
        ->divider()
        ->item(NavigationItem::make('B'));

    $payload = $nav->build();

    expect($payload)->toHaveCount(3)
        ->and($payload[1]['kind'])->toBe('divider');
});

it('auto-registers Resources into the right group', function (): void {
    $nav = (new Navigation)->autoRegisterFromResources([
        UserResource::class,
        DashboardResource::class,
    ]);

    $payload = $nav->build();

    $groupEntry = collect($payload)->firstWhere('kind', 'group');
    $itemEntries = collect($payload)->where('kind', 'item');

    expect($groupEntry['label'])->toBe('People')
        ->and($groupEntry['items'][0]['label'])->toBe('Users')
        ->and($itemEntries->pluck('label')->all())->toContain('Dashboard');
});

it('autoRegister(false) skips resource auto-registration', function (): void {
    $nav = (new Navigation)
        ->autoRegister(false)
        ->autoRegisterFromResources([UserResource::class]);

    expect($nav->build())->toBe([]);
});

it('does not duplicate a resource that is already registered manually', function (): void {
    $nav = (new Navigation)
        ->item(NavigationItem::resource(UserResource::class))
        ->autoRegisterFromResources([UserResource::class]);

    $payload = $nav->build();
    $userMatches = collect($payload)
        ->flatMap(fn (array $entry) => $entry['kind'] === 'group' ? $entry['items'] : [$entry])
        ->where('label', 'Users');

    expect($userMatches)->toHaveCount(1);
});
