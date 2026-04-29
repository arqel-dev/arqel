<?php

declare(strict_types=1);

use Arqel\Mcp\Tools\ListResourcesTool;

/**
 * Fixture: a healthy Resource-shaped class. The tool only consumes static
 * metadata, so we don't need to implement HasResource here — class-string
 * is what the registry stores. We bypass the registry's type guard by
 * constructing a stub registry implementation below for the unit tests.
 */
final class FixtureUsersResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\User';
    }

    public static function getSlug(): string
    {
        return 'users';
    }

    public static function getLabel(): string
    {
        return 'User';
    }

    public static function getPluralLabel(): string
    {
        return 'Users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
}

final class FixturePostsResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\Post';
    }

    public static function getSlug(): string
    {
        return 'posts';
    }

    public static function getLabel(): string
    {
        return 'Post';
    }

    public static function getPluralLabel(): string
    {
        return 'Posts';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}

final class FixtureBrokenResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\Broken';
    }

    public static function getSlug(): string
    {
        return 'broken';
    }

    public static function getLabel(): string
    {
        throw new RuntimeException('label boom');
    }

    public static function getPluralLabel(): string
    {
        return 'Broken';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}

/**
 * Resolver factory: returns a closure that yields the given class-strings.
 * The tool's constructor accepts this closure so unit tests bypass the
 * `final` ResourceRegistry (which cannot be subclassed) and the registry's
 * `HasResource` type guard.
 *
 * @param array<int, class-string> $classes
 *
 * @return Closure(): array<int, class-string>
 */
function fakeResolverFor(array $classes): Closure
{
    return fn (): array => $classes;
}

it('exposes the canonical tool schema', function (): void {
    $tool = new ListResourcesTool;

    expect($tool->schema())->toBe([
        'name' => 'list_resources',
        'description' => 'List all Arqel Resources registered in the application',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [],
        ],
    ]);
});

it('serialises every registered resource with the full metadata payload', function (): void {
    $registry = fakeResolverFor([FixtureUsersResource::class, FixturePostsResource::class]);
    $tool = new ListResourcesTool($registry);

    $result = $tool([]);

    expect($result)->toHaveKey('resources')
        ->and($result['resources'])->toHaveCount(2)
        ->and($result['resources'][0])->toBe([
            'class' => FixtureUsersResource::class,
            'model' => 'App\\Models\\User',
            'slug' => 'users',
            'label' => 'User',
            'pluralLabel' => 'Users',
            'navigationGroup' => 'System',
        ])
        ->and($result['resources'][1])->toBe([
            'class' => FixturePostsResource::class,
            'model' => 'App\\Models\\Post',
            'slug' => 'posts',
            'label' => 'Post',
            'pluralLabel' => 'Posts',
            'navigationGroup' => null,
        ]);
});

it('returns an empty resources list when the registry is empty', function (): void {
    $tool = new ListResourcesTool(fakeResolverFor([]));

    expect($tool([]))->toBe(['resources' => []]);
});

it('silently skips resources whose metadata accessors throw', function (): void {
    $registry = fakeResolverFor([
        FixtureUsersResource::class,
        FixtureBrokenResource::class,
        FixturePostsResource::class,
    ]);
    $tool = new ListResourcesTool($registry);

    $result = $tool([]);

    expect($result['resources'])->toHaveCount(2)
        ->and($result['resources'][0]['class'])->toBe(FixtureUsersResource::class)
        ->and($result['resources'][1]['class'])->toBe(FixturePostsResource::class);
});

it('falls back to the container when no registry is injected', function (): void {
    $tool = new ListResourcesTool;

    $result = $tool([]);

    expect($result)->toHaveKey('resources')
        ->and($result['resources'])->toBeArray();
});
