<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Providers\NavigationCommandProvider;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\Post;
use Arqel\Core\Tests\Fixtures\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Issue #129 — the command palette (Cmd+K) listed a "Go to" navigation
 * command for every Resource ignoring the `viewAny` Policy, leaking
 * forbidden feature names/links even though the sidebar (#118) already
 * hid them. These tests assert {@see NavigationCommandProvider::provide()}
 * skips commands whose `viewAny` is denied (when a gate/policy exists)
 * while keeping the scaffold/no-policy baseline that lists everything,
 * mirroring the sidebar's symmetric guard.
 */
final class PaletteUsersResource extends Resource
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

final class PalettePostsResource extends Resource
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

final class PaletteDenyViewAnyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return false;
    }
}

final class PaletteAllowViewAnyPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }
}

/**
 * @param array<int, Arqel\Core\CommandPalette\Command> $commands
 *
 * @return array<int, string>
 */
function paletteCommandIds(array $commands): array
{
    return array_map(static fn ($command): string => $command->id, $commands);
}

it('hides a navigation command whose viewAny Policy denies the current user', function (): void {
    Gate::policy(Post::class, PaletteDenyViewAnyPolicy::class);

    $registry = new ResourceRegistry;
    $registry->register(PaletteUsersResource::class);
    $registry->register(PalettePostsResource::class);

    $provider = new NavigationCommandProvider($registry);
    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $ids = paletteCommandIds($provider->provide($user, ''));

    expect($ids)->toContain('nav:users')
        ->and($ids)->not->toContain('nav:posts');
});

it('lists every navigation command when no gate or policy exists (scaffold baseline)', function (): void {
    $registry = new ResourceRegistry;
    $registry->register(PaletteUsersResource::class);
    $registry->register(PalettePostsResource::class);

    $provider = new NavigationCommandProvider($registry);
    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $ids = paletteCommandIds($provider->provide($user, ''));

    expect($ids)->toContain('nav:users')
        ->and($ids)->toContain('nav:posts');
});

it('keeps a navigation command whose viewAny Policy allows the current user', function (): void {
    Gate::policy(User::class, PaletteAllowViewAnyPolicy::class);

    $registry = new ResourceRegistry;
    $registry->register(PaletteUsersResource::class);

    $provider = new NavigationCommandProvider($registry);
    $user = new GenericUser(['id' => 1, 'name' => 'Admin', 'email' => 'a@b.test']);

    $ids = paletteCommandIds($provider->provide($user, ''));

    expect($ids)->toContain('nav:users');
});
