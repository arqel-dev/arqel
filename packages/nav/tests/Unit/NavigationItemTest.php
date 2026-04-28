<?php

declare(strict_types=1);

use Arqel\Nav\NavigationItem;
use Arqel\Nav\Tests\Fixtures\DashboardResource;
use Arqel\Nav\Tests\Fixtures\UserResource;
use Illuminate\Auth\GenericUser;

it('builds a manual item with fluent setters', function (): void {
    $item = NavigationItem::make('Dashboard')
        ->icon('home')
        ->url('/admin')
        ->openInNewTab()
        ->sort(10);

    $payload = $item->toArray();

    expect($payload)->toMatchArray([
        'label' => 'Dashboard',
        'icon' => 'home',
        'url' => '/admin',
        'openInNewTab' => true,
        'sort' => 10,
        'visible' => true,
    ])
        ->and($payload)->not->toHaveKey('routeName');
});

it('switches to route mode and clears the URL', function (): void {
    $item = NavigationItem::make('Users')
        ->url('/users')
        ->route('admin.users.index', ['panel' => 'admin']);

    $payload = $item->toArray();

    expect($payload)->toHaveKey('routeName')
        ->and($payload['routeName'])->toBe('admin.users.index')
        ->and($payload['routeParams'])->toBe(['panel' => 'admin'])
        ->and($payload)->not->toHaveKey('url');
});

it('auto-fills label/icon/url/sort from a Resource', function (): void {
    $item = NavigationItem::resource(UserResource::class);

    expect($item->getLabel())->toBe('Users')
        ->and($item->getSort())->toBe(5)
        ->and($item->getResourceClass())->toBe(UserResource::class);

    $payload = $item->toArray();

    expect($payload['icon'])->toBe('user')
        ->and($payload['url'])->toBe('/users');
});

it('falls back to class basename when Resource exposes nothing', function (): void {
    $item = NavigationItem::resource(DashboardResource::class);

    expect($item->getLabel())->toBe('Dashboard');
});

it('respects visibility callback', function (): void {
    $user = new GenericUser(['id' => 1]);
    $item = NavigationItem::make('Admin')
        ->visible(fn ($u) => $u !== null);

    expect($item->isVisibleFor($user))->toBeTrue()
        ->and($item->isVisibleFor(null))->toBeFalse();
});

it('resolves a literal badge', function (): void {
    expect(NavigationItem::make('x')->badge(7)->resolveBadge())->toBe(7)
        ->and(NavigationItem::make('x')->badge('NEW')->resolveBadge())->toBe('NEW');
});

it('resolves a Closure badge and discards non-scalar returns', function (): void {
    expect(NavigationItem::make('x')->badge(fn () => 42)->resolveBadge())->toBe(42)
        ->and(NavigationItem::make('x')->badge(fn () => null)->resolveBadge())->toBeNull()
        ->and(NavigationItem::make('x')->badge(fn () => ['a'])->resolveBadge())->toBeNull();
});
