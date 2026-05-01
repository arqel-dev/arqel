<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Versioning\Tests\Fixtures\Article;
use Arqel\Versioning\Tests\Fixtures\ArticleResource;
use Arqel\Versioning\Tests\Fixtures\PlainArticle;
use Arqel\Versioning\Tests\Fixtures\PlainArticleResource;
use Arqel\Versioning\Tests\TestCase;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Testing\TestResponse;

function authedUserForVersionHistory(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test User', 'email' => 't@e.dev']);

    return $user;
}

/**
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function getVersionHistory(TestCase $case, string $resourceSlug, int|string $id, string $query = ''): TestResponse
{
    $url = "/admin/{$resourceSlug}/{$id}/versions".($query !== '' ? "?{$query}" : '');

    return $case->actingAs(authedUserForVersionHistory())->getJson($url);
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with versions ordered desc on the happy path', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'V2']);
    $article->update(['title' => 'V3']);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'articles', $article->id);

    $response->assertOk();
    $body = (array) $response->json();

    expect($body)->toHaveKey('versions');
    expect($body)->toHaveKey('meta');

    /** @var array{data: array<int, array<string, mixed>>, total: int} $versions */
    $versions = $body['versions'];
    expect($versions['total'])->toBe(3);
    expect($versions['data'])->toHaveCount(3);

    $first = $versions['data'][0];
    expect($first)->toHaveKeys(['id', 'created_at', 'changes_summary', 'changes', 'user', 'is_initial']);
    expect($first)->not->toHaveKey('payload');
});

it('returns 404 when ResourceRegistry is not bound', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);

    app()->offsetUnset(ResourceRegistry::class);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'articles', $article->id);

    $response->assertStatus(404);
});

it('returns 404 when the resource slug is not registered', function (): void {
    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'unknown-slug', $article->id);

    $response->assertStatus(404);
});

it('returns 404 when the record does not exist', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'articles', 999_999);

    $response->assertStatus(404);
});

it('returns 422 when the model does not use the Versionable trait', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(PlainArticleResource::class);

    $plain = PlainArticle::create(['title' => 'plain']);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'plain-articles', $plain->id);

    $response->assertStatus(422);
});

it('respects per_page paginating the result set', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'V2']);
    $article->update(['title' => 'V3']);
    $article->update(['title' => 'V4']);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'articles', $article->id, 'per_page=2');

    $response->assertOk();
    $body = (array) $response->json();

    /** @var array<string, mixed> $versions */
    $versions = $body['versions'];
    expect($versions['per_page'])->toBe(2);
    expect($versions['total'])->toBe(4);
    expect($versions['last_page'])->toBe(2);
    /** @var array<int, mixed> $data */
    $data = $versions['data'];
    expect($data)->toHaveCount(2);
});

it('omits payload by default and exposes it with ?include=payload', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'Secret', 'body' => 'leak', 'status' => 'draft']);

    /** @var TestCase $this */
    $without = getVersionHistory($this, 'articles', $article->id);
    $without->assertOk();
    /** @var array<string, mixed> $bodyA */
    $bodyA = (array) $without->json();
    /** @var array<string, mixed> $versionsA */
    $versionsA = $bodyA['versions'];
    /** @var array<int, array<string, mixed>> $dataA */
    $dataA = $versionsA['data'];
    expect($dataA[0])->not->toHaveKey('payload');

    /** @var TestCase $this */
    $with = getVersionHistory($this, 'articles', $article->id, 'include=payload');
    $with->assertOk();
    /** @var array<string, mixed> $bodyB */
    $bodyB = (array) $with->json();
    /** @var array<string, mixed> $versionsB */
    $versionsB = $bodyB['versions'];
    /** @var array<int, array<string, mixed>> $dataB */
    $dataB = $versionsB['data'];
    expect($dataB[0])->toHaveKey('payload');
    /** @var array<string, mixed> $payload */
    $payload = $dataB[0]['payload'];
    expect($payload['title'] ?? null)->toBe('Secret');
});

it('exposes meta.keep_versions reflecting the current config', function (): void {
    config()->set('arqel-versioning.keep_versions', 7);

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);

    /** @var TestCase $this */
    $response = getVersionHistory($this, 'articles', $article->id);

    $response->assertOk();
    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    /** @var array<string, mixed> $meta */
    $meta = $body['meta'];
    expect($meta['keep_versions'])->toBe(7);
    expect($meta['total'])->toBe(1);
});
