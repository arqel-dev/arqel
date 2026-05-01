<?php

declare(strict_types=1);

use Arqel\Versioning\Jobs\PruneOldVersionsJob;
use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;
use Arqel\Versioning\Tests\TestCase;
use Arqel\Versioning\VersionPresenter;
use Illuminate\Support\Facades\Auth;

/**
 * Coverage gaps suite (VERS-007).
 *
 * Cobre branches que a suíte principal deixava sem exercício:
 * - resolveAuditUserId() com callable resolver e ausência total.
 * - pruneOldVersionsFor() com strategy != 'count' e keep=0 (unbounded).
 * - bootVersionable() ignorando saves sem mudanças efetivas.
 * - Version::user() defensivo para classe inexistente / não-Model.
 * - VersionPresenter::summarize() com 1 field, 5+ fields, e changes vazias.
 * - VersionHistoryController clamping per_page acima do max.
 * - PruneVersionsCommand verbose output em count > 0.
 * - PruneOldVersionsJob round-trip serialize/unserialize.
 */
final class VersioningCoverageGapsResolver
{
    public static function resolve(): int
    {
        return 4242;
    }

    public static function resolveString(): string
    {
        return 'not-an-int';
    }
}

it('resolves audit user via configured callable resolver', function (): void {
    config()->set(
        'arqel-versioning.audit_user',
        VersioningCoverageGapsResolver::class.'::resolve',
    );

    $article = Article::create(['title' => 'AuditUser', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    expect($version->created_by_user_id)->toBe(4242);
});

it('falls back to null when audit_user callable returns non-int', function (): void {
    config()->set(
        'arqel-versioning.audit_user',
        VersioningCoverageGapsResolver::class.'::resolveString',
    );

    Auth::shouldReceive('id')->andReturn(null)->byDefault();

    $article = Article::create(['title' => 'NonInt', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    expect($version->created_by_user_id)->toBeNull();
});

it('returns null user id when neither audit_user nor Auth is configured', function (): void {
    config()->set('arqel-versioning.audit_user', null);

    $article = Article::create(['title' => 'NoAuth', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    expect($version->created_by_user_id)->toBeNull();
});

it('skips pruning when prune_strategy is not "count"', function (): void {
    config()->set('arqel-versioning.prune_strategy', 'time');
    config()->set('arqel-versioning.keep_versions', 1);

    $article = Article::create(['title' => 'P0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'P1']);
    $article->update(['title' => 'P2']);

    // strategy != 'count' → early return, nada é deletado
    expect($article->versions()->count())->toBe(3);

    expect($article->pruneOldVersions())->toBe(0);
});

it('treats keep_versions=0 as unbounded (no prune)', function (): void {
    config()->set('arqel-versioning.prune_strategy', 'count');
    config()->set('arqel-versioning.keep_versions', 0);

    $article = Article::create(['title' => 'U0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'U1']);
    $article->update(['title' => 'U2']);
    $article->update(['title' => 'U3']);

    expect($article->versions()->count())->toBe(4);
    expect($article->pruneOldVersions())->toBe(0);
});

it('does not version a save when only timestamps change (wasChanged false branch)', function (): void {
    $article = Article::create(['title' => 'TS', 'body' => 'b', 'status' => 'draft']);

    $countBefore = $article->versions()->count();

    // Touch updates only updated_at — `updating` hook filtra timestamps,
    // diff fica vazio, `updated` hook early-returns sem gravar version.
    $article->touch();

    expect($article->versions()->count())->toBe($countBefore);
});

it('returns null from Version::user() when user_model is not an Eloquent Model', function (): void {
    config()->set('arqel-versioning.user_model', stdClass::class);

    $version = new Version;

    expect($version->user())->toBeNull();
});

it('summarizes a single-field diff with singular noun', function (): void {
    $article = Article::create(['title' => 'S0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'S1']);

    /** @var Version $latest */
    $latest = $article->versions()->first();

    $payload = VersionPresenter::toArray($latest);

    expect($payload['changes_summary'])->toBe('Changed 1 field: title');
});

it('summarizes a 5-field diff listing each changed field', function (): void {
    // Article só tem title/body/status, então sintetizamos diff direto.
    $article = Article::create(['title' => 'M', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();
    $version->changes = [
        'a' => ['x', 'y'],
        'b' => ['x', 'y'],
        'c' => ['x', 'y'],
        'd' => ['x', 'y'],
        'e' => ['x', 'y'],
    ];

    $payload = VersionPresenter::toArray($version);

    expect($payload['changes_summary'])->toBe('Changed 5 fields: a, b, c, d, e');
});

it('summarizes empty-changes array as "No changes"', function (): void {
    $article = Article::create(['title' => 'E', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();
    $version->changes = [];

    $payload = VersionPresenter::toArray($version);

    expect($payload['changes_summary'])->toBe('No changes');
    expect($payload['is_initial'])->toBeFalse();
});

it('clamps per_page above the max to the controller ceiling', function (): void {
    /** @var Arqel\Core\Resources\ResourceRegistry $registry */
    $registry = app(Arqel\Core\Resources\ResourceRegistry::class);
    $registry->clear();
    $registry->register(Arqel\Versioning\Tests\Fixtures\ArticleResource::class);

    $article = Article::create(['title' => 'P', 'body' => 'b', 'status' => 'draft']);

    $user = new Illuminate\Foundation\Auth\User;
    $user->forceFill(['id' => 1, 'name' => 'tester', 'email' => 't@e.dev']);

    /** @var TestCase $this */
    $response = $this->actingAs($user)->getJson(
        "/admin/articles/{$article->id}/versions?per_page=200",
    );

    $response->assertOk();

    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    /** @var array<string, mixed> $versions */
    $versions = $body['versions'];

    // Controller PER_PAGE_MAX = 100.
    expect($versions['per_page'])->toBe(100);
});

it('clamps per_page below 1 back to the default', function (): void {
    /** @var Arqel\Core\Resources\ResourceRegistry $registry */
    $registry = app(Arqel\Core\Resources\ResourceRegistry::class);
    $registry->clear();
    $registry->register(Arqel\Versioning\Tests\Fixtures\ArticleResource::class);

    $article = Article::create(['title' => 'D', 'body' => 'b', 'status' => 'draft']);

    $user = new Illuminate\Foundation\Auth\User;
    $user->forceFill(['id' => 1, 'name' => 'tester', 'email' => 't@e.dev']);

    /** @var TestCase $this */
    $response = $this->actingAs($user)->getJson(
        "/admin/articles/{$article->id}/versions?per_page=0",
    );

    $response->assertOk();

    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    /** @var array<string, mixed> $versions */
    $versions = $body['versions'];

    // Default = 20.
    expect($versions['per_page'])->toBe(20);
});

it('emits Pruned output line when count > 0 (verbose smoke)', function (): void {
    config()->set('arqel-versioning.keep_versions', 999);

    $article = Article::create(['title' => 'P0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'P1']);
    $article->update(['title' => 'P2']);

    /** @var TestCase $this */
    $this->artisan('arqel:versions:prune', ['--keep' => 1])
        ->expectsOutputToContain('Pruned')
        ->assertSuccessful();
});

it('round-trips PruneOldVersionsJob through serialize/unserialize', function (): void {
    $job = new PruneOldVersionsJob(days: 30, keep: 10);

    $serialized = serialize($job);
    /** @var PruneOldVersionsJob $restored */
    $restored = unserialize($serialized);

    expect($restored)->toBeInstanceOf(PruneOldVersionsJob::class);
    expect($restored->days)->toBe(30);
    expect($restored->keep)->toBe(10);
});
