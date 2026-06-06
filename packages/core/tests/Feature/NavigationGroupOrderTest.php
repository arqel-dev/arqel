<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Resources\Resource;

/**
 * Fixtures with explicit groups + sorts so we can assert the navigation
 * payload orders GROUPS by Panel::navigationGroups() while keeping the
 * within-group ordering by per-item navigationSort.
 */
final class ContentAResource extends Resource
{
    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 5;

    public static function getSlug(): string
    {
        return 'content-a';
    }

    public static function getPluralLabel(): string
    {
        return 'Content A';
    }

    public function fields(): array
    {
        return [];
    }
}

final class ContentBResource extends Resource
{
    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 1;

    public static function getSlug(): string
    {
        return 'content-b';
    }

    public static function getPluralLabel(): string
    {
        return 'Content B';
    }

    public function fields(): array
    {
        return [];
    }
}

final class SystemResource extends Resource
{
    public static ?string $navigationGroup = 'System';

    public static ?int $navigationSort = 99;

    public static function getSlug(): string
    {
        return 'system';
    }

    public static function getPluralLabel(): string
    {
        return 'System';
    }

    public function fields(): array
    {
        return [];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function buildNav(Panel $panel): array
{
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'buildNavigation');
    $ref->setAccessible(true);

    /** @var array<int, array<string, mixed>> $payload */
    $payload = $ref->invoke($mw, $panel);

    return $payload;
}

/**
 * @param array<int, array<string, mixed>> $items
 *
 * @return array<int, string>
 */
function groupSequence(array $items): array
{
    $seen = [];
    $sequence = [];
    foreach ($items as $item) {
        $group = $item['group'] ?? null;
        $key = is_string($group) ? $group : '';
        if (! in_array($key, $seen, true)) {
            $seen[] = $key;
            $sequence[] = $key;
        }
    }

    return $sequence;
}

it('orders groups by Panel::navigationGroups() when set', function (): void {
    // Reverse of the natural (min-item-sort) order: System's items sort
    // after Content's, yet the explicit list demands System first.
    $panel = (new Panel('admin'))
        ->navigationGroups(['System', 'Content'])
        ->resources([
            ContentAResource::class,
            ContentBResource::class,
            SystemResource::class,
        ]);

    $items = buildNav($panel);

    // Group order follows the explicit list (System before Content).
    expect(groupSequence($items))->toBe(['System', 'Content']);

    // Within each group items remain ordered by navigationSort.
    $contentLabels = array_values(array_map(
        fn (array $i): string => (string) $i['label'],
        array_filter($items, fn (array $i): bool => $i['group'] === 'Content'),
    ));
    expect($contentLabels)->toBe(['Content B', 'Content A']);
});

it('keeps the per-item-sort fallback when navigationGroups() is not set', function (): void {
    $panel = (new Panel('admin'))
        ->resources([
            ContentAResource::class,
            ContentBResource::class,
            SystemResource::class,
        ]);

    $items = buildNav($panel);

    // No explicit list: pure per-item navigationSort ordering (today's
    // behavior). ContentB(1) < ContentA(5) < System(99).
    $labels = array_map(fn (array $i): string => (string) $i['label'], $items);
    expect($labels)->toBe(['Content B', 'Content A', 'System']);
});
