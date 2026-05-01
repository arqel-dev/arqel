<?php

declare(strict_types=1);

use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;
use Arqel\Versioning\VersionPresenter;

/**
 * Helper for the VersionPresenter unit suite — `versions()->first()`
 * is `Version|null`; the tests rely on at least one snapshot existing.
 */
function latestVersionOrFail(Article $article): Version
{
    /** @var Version|null $version */
    $version = $article->versions()->first();
    if ($version === null) {
        throw new RuntimeException('Expected at least one version on the fixture.');
    }

    return $version;
}

it('summarizes a populated diff with field count and names', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'B', 'body' => 'c', 'status' => 'published']);

    $latest = latestVersionOrFail($article);

    $payload = VersionPresenter::toArray($latest);

    expect($payload['changes_summary'])->toContain('Changed 3 fields');
    expect($payload['changes_summary'])->toContain('title');
    expect($payload['changes_summary'])->toContain('body');
    expect($payload['changes_summary'])->toContain('status');
    expect($payload['is_initial'])->toBeFalse();
});

it('summarizes the initial insert as Created and flags is_initial true', function (): void {
    $article = Article::create(['title' => 'Initial', 'body' => 'b', 'status' => 'draft']);

    $first = latestVersionOrFail($article);

    $payload = VersionPresenter::toArray($first);

    expect($payload['changes_summary'])->toBe('Created');
    expect($payload['is_initial'])->toBeTrue();
    expect($payload['changes'])->toBeNull();
});

it('flags is_initial false on subsequent versions', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'B']);

    $latest = latestVersionOrFail($article);

    $payload = VersionPresenter::toArray($latest);

    expect($payload['is_initial'])->toBeFalse();
    expect($payload['changes_summary'])->toContain('Changed 1 field');
});

it('emits null user when no created_by_user_id is recorded', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);

    $first = latestVersionOrFail($article);

    $payload = VersionPresenter::toArray($first);

    expect($payload['user'])->toBeNull();
});

it('emits user.id when created_by_user_id is set even without resolvable model', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);

    $first = latestVersionOrFail($article);
    $first->created_by_user_id = 42;
    $first->save();
    $first->refresh();

    $payload = VersionPresenter::toArray($first);

    expect($payload['user'])->not->toBeNull();
    /** @var array{id: int, name: string|null} $user */
    $user = $payload['user'];
    expect($user['id'])->toBe(42);
});

it('omits payload by default and includes it when requested', function (): void {
    $article = Article::create(['title' => 'Secret', 'body' => 'b', 'status' => 'draft']);

    $first = latestVersionOrFail($article);

    $without = VersionPresenter::toArray($first);
    expect($without)->not->toHaveKey('payload');

    $with = VersionPresenter::toArray($first, includePayload: true);
    expect($with)->toHaveKey('payload');
    expect($with['payload'] ?? null)->not->toBeNull();
    /** @var array<string, mixed> $payload */
    $payload = $with['payload'] ?? [];
    expect($payload['title'] ?? null)->toBe('Secret');
});
