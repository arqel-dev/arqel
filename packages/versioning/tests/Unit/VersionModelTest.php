<?php

declare(strict_types=1);

use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;

it('casts payload and changes as arrays and created_at as datetime', function (): void {
    $article = Article::create(['title' => 'Cast', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'Cast2']);

    /** @var Version $version */
    $version = $article->versions()->first();

    expect($version->payload)->toBeArray();
    expect($version->changes)->toBeArray();
    expect($version->created_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('resolves the morphTo relationship back to the source model', function (): void {
    $article = Article::create(['title' => 'Morph', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    /** @var Article $resolved */
    $resolved = $version->versionable;

    expect($resolved)->toBeInstanceOf(Article::class);
    expect($resolved->id)->toBe($article->id);
});

it('disables timestamps so the table stays append-only', function (): void {
    $version = new Version;

    expect($version->timestamps)->toBeFalse();
    expect($version->getTable())->toBe('arqel_versions');
});

it('returns null from user() when configured user model does not exist', function (): void {
    config()->set('arqel-versioning.user_model', 'App\\Does\\NotExist');

    $version = new Version;

    expect($version->user())->toBeNull();
});
