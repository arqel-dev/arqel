<?php

declare(strict_types=1);

use Arqel\Mcp\Tools\DescribeResourceTool;

/**
 * Fixture: full healthy Resource-shaped class. Uses a unique class name
 * to avoid colliding with `FixtureUsersResource` in ListResourcesToolTest
 * when both files are included in the same Pest run.
 */
final class FixtureMcp004UsersResource
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

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }
}

/**
 * Fixture: navigation icon throws — exercises the defensive try/catch on
 * a nullable accessor (returns null, never propagates).
 */
final class FixtureMcp004BrokenIconResource
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

    public static function getNavigationIcon(): ?string
    {
        throw new RuntimeException('icon boom');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return null;
    }
}

it('exposes the canonical tool schema', function (): void {
    $tool = new DescribeResourceTool;

    expect($tool->schema())->toBe([
        'name' => 'describe_resource',
        'description' => 'Get detailed information about a specific Arqel Resource',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'The Resource slug (e.g., "users")',
                ],
            ],
            'required' => ['slug'],
        ],
    ]);
});

it('returns the full metadata payload for a known slug', function (): void {
    $tool = new DescribeResourceTool(
        fn (string $slug): ?string => $slug === 'users'
            ? FixtureMcp004UsersResource::class
            : null,
    );

    $result = $tool(['slug' => 'users']);

    expect($result)->toHaveKeys([
        'class', 'model', 'slug', 'label', 'pluralLabel',
        'navigationIcon', 'navigationGroup', 'navigationSort',
    ])
        ->and($result['class'])->toBe(FixtureMcp004UsersResource::class)
        ->and($result['model'])->toBe('App\\Models\\User')
        ->and($result['slug'])->toBe('users')
        ->and($result['label'])->toBe('User')
        ->and($result['pluralLabel'])->toBe('Users')
        ->and($result['navigationIcon'])->toBe('heroicon-o-users')
        ->and($result['navigationGroup'])->toBe('System')
        ->and($result['navigationSort'])->toBe(10);
});

it('throws InvalidArgumentException when slug is missing', function (): void {
    $tool = new DescribeResourceTool(fn (string $slug): ?string => null);

    $tool([]);
})->throws(InvalidArgumentException::class, "'slug' parameter is required and must be a string");

it('throws InvalidArgumentException when slug is not a string', function (): void {
    $tool = new DescribeResourceTool(fn (string $slug): ?string => null);

    $tool(['slug' => 42]);
})->throws(InvalidArgumentException::class, "'slug' parameter is required and must be a string");

it('throws RuntimeException when slug is unknown', function (): void {
    $tool = new DescribeResourceTool(fn (string $slug): ?string => null);

    $tool(['slug' => 'ghosts']);
})->throws(RuntimeException::class, "Resource 'ghosts' not found");

it('degrades gracefully when an optional metadata accessor throws', function (): void {
    $tool = new DescribeResourceTool(
        fn (string $slug): ?string => FixtureMcp004BrokenIconResource::class,
    );

    $result = $tool(['slug' => 'posts']);

    // navigationIcon is nullable and threw -> null sentinel
    expect($result['navigationIcon'])->toBeNull()
        // Other fields populated normally
        ->and($result['class'])->toBe(FixtureMcp004BrokenIconResource::class)
        ->and($result['model'])->toBe('App\\Models\\Post')
        ->and($result['slug'])->toBe('posts')
        ->and($result['label'])->toBe('Post')
        ->and($result['pluralLabel'])->toBe('Posts')
        ->and($result['navigationGroup'])->toBeNull()
        ->and($result['navigationSort'])->toBeNull();
});
