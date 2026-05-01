<?php

declare(strict_types=1);

use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;

it('creates a version on insert and on each effective update', function (): void {
    $article = Article::create(['title' => 'Hello', 'body' => 'World', 'status' => 'draft']);

    $article->update(['title' => 'Hello v2']);
    $article->update(['status' => 'published']);

    expect($article->versions()->count())->toBe(3);
});

it('snapshots the full payload in each version', function (): void {
    $article = Article::create(['title' => 'Snap', 'body' => 'Body', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    expect($version->payload)->toMatchArray([
        'title' => 'Snap',
        'body' => 'Body',
        'status' => 'draft',
    ]);
    expect($version->payload)->toHaveKey('id');
});

it('records only changed fields in the diff column', function (): void {
    $article = Article::create(['title' => 'Initial', 'body' => 'Body', 'status' => 'draft']);
    $article->update(['title' => 'Updated']);

    /** @var Version $latest */
    $latest = $article->versions()->first();

    $changes = $latest->changes ?? [];
    expect($changes)->toHaveKey('title');
    expect($changes['title'] ?? null)->toBe(['Initial', 'Updated']);
    expect($changes)->not->toHaveKey('body');
    expect($changes)->not->toHaveKey('status');
});

it('skips creating a version when nothing changed on update', function (): void {
    $article = Article::create(['title' => 'NoOp', 'body' => 'B', 'status' => 'draft']);

    $article->title = 'NoOp';
    $article->save();

    expect($article->versions()->count())->toBe(1);
});

it('restores the model attributes from a previous version', function (): void {
    $article = Article::create(['title' => 'V1', 'body' => 'BodyA', 'status' => 'draft']);
    $article->update(['title' => 'V2', 'body' => 'BodyB']);

    /** @var Version $first */
    $first = $article->versions()->reorder('id', 'asc')->first();

    expect($article->restoreToVersion($first))->toBeTrue();

    $article->refresh();
    expect($article->title)->toBe('V1');
    expect($article->body)->toBe('BodyA');
});

it('creates a new version when restoring (non-destructive)', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'B', 'status' => 'draft']);
    $article->update(['title' => 'B']);

    expect($article->versions()->count())->toBe(2);

    /** @var Version $first */
    $first = $article->versions()->reorder('id', 'asc')->first();

    $article->restoreToVersion($first);

    expect($article->versions()->count())->toBe(3);
});

it('prunes old versions when keep_versions is set', function (): void {
    config()->set('arqel-versioning.keep_versions', 2);

    $article = Article::create(['title' => 'P0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'P1']);
    $article->update(['title' => 'P2']);
    $article->update(['title' => 'P3']);

    expect($article->versions()->count())->toBe(2);

    /** @var Version $latest */
    $latest = $article->versions()->first();
    expect($latest->payload['title'])->toBe('P3');
});

it('is a no-op when versioning is disabled in config', function (): void {
    config()->set('arqel-versioning.enabled', false);

    $article = Article::create(['title' => 'Off', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'Off2']);

    expect($article->versions()->count())->toBe(0);
});

it('returns false when restoring a version that belongs to another record', function (): void {
    $a = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);
    $b = Article::create(['title' => 'B', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $versionOfA */
    $versionOfA = $a->versions()->first();

    expect($b->restoreToVersion($versionOfA))->toBeFalse();
});

it('returns false when restoring a non-existing version id', function (): void {
    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);

    expect($article->restoreToVersion(99999))->toBeFalse();
});

it('exposes currentVersion as alias for the latest snapshot', function (): void {
    $article = Article::create(['title' => 'C0', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'C1']);

    expect($article->currentVersion()?->payload['title'])->toBe('C1');
});
