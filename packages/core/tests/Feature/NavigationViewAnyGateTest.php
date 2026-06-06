<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Post;
use Arqel\Core\Tests\Fixtures\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Issue #118 — the sidebar listed every Resource regardless of the
 * `viewAny` Policy, leaking forbidden feature names/links. These tests
 * assert `buildNavigation()` skips nav items whose `viewAny` is denied
 * (when a gate or policy exists) while keeping the scaffold/no-policy
 * baseline that lists everything.
 */
final class NavUsersResource extends Resource
{
    public static string $model = User::class;

    public static function getSlug(): string
    {
        return 'users';
    }

    public static function getPluralLabel(): string
    {
        return 'Users';
    }

    public function fields(): array
    {
        return [];
    }
}

final class NavPostsResource extends Resource
{
    public static string $model = Post::class;

    public static function getSlug(): string
    {
        return 'posts';
    }

    public static function getPluralLabel(): string
    {
        return 'Posts';
    }

    public function fields(): array
    {
        return [];
    }
}

/**
 * Policy that denies `viewAny` for the model it is registered against.
 */
final class DenyViewAnyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return false;
    }
}

/**
 * Policy that allows `viewAny`.
 */
final class AllowViewAnyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function buildNavFor(Panel $panel, ?Authenticatable $user): array
{
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'buildNavigation');
    $ref->setAccessible(true);

    /** @var array<int, array<string, mixed>> $payload */
    $payload = $ref->invoke($mw, $panel, $user);

    return $payload;
}

/**
 * @param array<int, array<string, mixed>> $items
 *
 * @return array<int, string>
 */
function navSlugs(array $items): array
{
    return array_map(static function (array $item): string {
        $url = is_string($item['url']) ? $item['url'] : '';

        return (string) str($url)->afterLast('/');
    }, $items);
}

it('hides a Resource whose viewAny Policy denies the current user', function (): void {
    Gate::policy(Post::class, DenyViewAnyPolicy::class);

    $panel = (new Panel('admin'))->resources([
        NavUsersResource::class,
        NavPostsResource::class,
    ]);

    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $slugs = navSlugs(buildNavFor($panel, $user));

    expect($slugs)->toContain('users')
        ->and($slugs)->not->toContain('posts');
});

it('lists every Resource when no gate or policy exists (scaffold baseline)', function (): void {
    $panel = (new Panel('admin'))->resources([
        NavUsersResource::class,
        NavPostsResource::class,
    ]);

    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $slugs = navSlugs(buildNavFor($panel, $user));

    expect($slugs)->toContain('users')
        ->and($slugs)->toContain('posts');
});

it('keeps a Resource whose viewAny Policy allows the current user', function (): void {
    Gate::policy(User::class, AllowViewAnyPolicy::class);

    $panel = (new Panel('admin'))->resources([
        NavUsersResource::class,
    ]);

    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $slugs = navSlugs(buildNavFor($panel, $user));

    expect($slugs)->toContain('users');
});
