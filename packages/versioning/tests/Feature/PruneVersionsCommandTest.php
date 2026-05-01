<?php

declare(strict_types=1);

use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;
use Illuminate\Support\Carbon;

it('prunes versions older than --days', function (): void {
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'b']);
    $article->update(['title' => 'c']);

    // Backdate the first version to 30 days ago.
    /** @var Version $oldest */
    $oldest = $article->versions()->orderBy('id', 'asc')->first();
    $oldest->forceFill(['created_at' => Carbon::now()->subDays(30)])->save();

    $this->artisan('arqel:versions:prune', ['--days' => 7])
        ->assertSuccessful();

    expect(Version::query()->whereKey($oldest->id)->exists())->toBeFalse();
    expect(Version::query()->count())->toBeGreaterThan(0);
});

it('keeps top-N most recent per record with --keep', function (): void {
    config()->set('arqel-versioning.keep_versions', 999);
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'b']);
    $article->update(['title' => 'c']);
    $article->update(['title' => 'd']);

    expect($article->versions()->count())->toBe(4);

    $this->artisan('arqel:versions:prune', ['--keep' => 2])
        ->assertSuccessful();

    expect($article->versions()->count())->toBe(2);
});

it('does not delete on --dry-run', function (): void {
    config()->set('arqel-versioning.keep_versions', 999);
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'b']);
    $article->update(['title' => 'c']);

    $before = Version::query()->count();

    $this->artisan('arqel:versions:prune', ['--keep' => 1, '--dry-run' => true])
        ->expectsOutputToContain('[DRY RUN]')
        ->assertSuccessful();

    expect(Version::query()->count())->toBe($before);
});

it('uses config keep_versions when no flag is provided', function (): void {
    config()->set('arqel-versioning.keep_versions', 1);

    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    // Each update auto-prunes via the trait, so manually re-insert older
    // rows to simulate accumulated history a config-only run should clean.
    Version::query()->insert([
        'versionable_type' => $article->getMorphClass(),
        'versionable_id' => $article->getKey(),
        'payload' => json_encode(['title' => 'fake', 'body' => 'b', 'status' => 'draft']),
        'changes' => null,
        'created_by_user_id' => null,
        'reason' => null,
        'created_at' => Carbon::now()->subMinute(),
    ]);

    expect($article->versions()->count())->toBeGreaterThan(1);

    $this->artisan('arqel:versions:prune')
        ->assertSuccessful();

    expect($article->versions()->count())->toBe(1);
});

it('is idempotent — running twice does not over-delete', function (): void {
    config()->set('arqel-versioning.keep_versions', 999);
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'b']);
    $article->update(['title' => 'c']);

    $this->artisan('arqel:versions:prune', ['--keep' => 2])->assertSuccessful();
    $afterFirst = Version::query()->count();

    $this->artisan('arqel:versions:prune', ['--keep' => 2])->assertSuccessful();
    $afterSecond = Version::query()->count();

    expect($afterSecond)->toBe($afterFirst);
});
