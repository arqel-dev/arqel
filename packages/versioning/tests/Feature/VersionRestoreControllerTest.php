<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Versioning\Http\Controllers\VersionRestoreController;
use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;
use Arqel\Versioning\Tests\Fixtures\ArticleResource;
use Arqel\Versioning\Tests\Fixtures\PlainWidget;
use Arqel\Versioning\Tests\Fixtures\PlainWidgetResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->register(ArticleResource::class);

    // Register restore route without web/auth middleware so tests can post freely.
    Route::post(
        '/admin/{resource}/{id}/versions/{versionId}/restore',
        VersionRestoreController::class,
    )->name('arqel.versioning.restore.test');
});

it('restores a record to a previous version (200 happy path)', function (): void {
    $article = Article::create(['title' => 'v1', 'body' => 'b1', 'status' => 'draft']);
    $article->update(['title' => 'v2']);
    $article->update(['title' => 'v3']);

    /** @var Version $first */
    $first = Version::query()->where('versionable_type', $article->getMorphClass())->where('versionable_id', $article->getKey())->orderBy('id', 'asc')->first();

    $countBefore = $article->versions()->count();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'articles',
        'id' => $article->getKey(),
        'versionId' => $first->id,
    ]));

    $response->assertOk();
    $response->assertJsonPath('restored', true);

    /** @var Article $fresh */
    $fresh = Article::query()->findOrFail($article->getKey());
    expect($fresh->title)->toBe('v1');

    expect($fresh->versions()->count())->toBe($countBefore + 1);
});

it('returns 404 when slug is unknown', function (): void {
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    /** @var Version $version */
    $version = $article->versions()->first();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'unknown-slug',
        'id' => $article->getKey(),
        'versionId' => $version->id,
    ]));

    $response->assertNotFound();
});

it('returns 404 when record does not exist', function (): void {
    $article = Article::create(['title' => 'a', 'body' => 'b', 'status' => 'draft']);
    /** @var Version $version */
    $version = $article->versions()->first();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'articles',
        'id' => 999_999,
        'versionId' => $version->id,
    ]));

    $response->assertNotFound();
});

it('returns 404 when version does not belong to record', function (): void {
    $a = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);
    $b = Article::create(['title' => 'B', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $bVersion */
    $bVersion = $b->versions()->first();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'articles',
        'id' => $a->getKey(),
        'versionId' => $bVersion->id,
    ]));

    $response->assertNotFound();
});

it('returns 422 when model does not use Versionable', function (): void {
    Schema::create('plain_widgets', static function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->register(PlainWidgetResource::class);

    $row = PlainWidget::create(['name' => 'x']);

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'plain-widgets',
        'id' => $row->getKey(),
        'versionId' => 1,
    ]));

    expect($response->status())->toBe(422);
});

it('returns 403 when Gate update ability denies', function (): void {
    Gate::define('update', static fn (?Model $user, Model $record): bool => false);

    $article = Article::create(['title' => 'gated', 'body' => 'b', 'status' => 'draft']);
    /** @var Version $version */
    $version = $article->versions()->first();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'articles',
        'id' => $article->getKey(),
        'versionId' => $version->id,
    ]));

    $response->assertForbidden();
});

it('returns the new version id created by the restore', function (): void {
    $article = Article::create(['title' => 'orig', 'body' => 'b', 'status' => 'draft']);
    $article->update(['title' => 'changed']);

    $countBefore = Version::query()->count();

    /** @var Version $first */
    $first = Version::query()->where('versionable_type', $article->getMorphClass())->where('versionable_id', $article->getKey())->orderBy('id', 'asc')->first();

    $response = $this->postJson(route('arqel.versioning.restore.test', [
        'resource' => 'articles',
        'id' => $article->getKey(),
        'versionId' => $first->id,
    ]));

    $response->assertOk();
    $newId = $response->json('new_version_id');
    expect($newId)->toBeInt();
    expect(Version::query()->count())->toBe($countBefore + 1);
});
