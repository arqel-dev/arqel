<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Versioning\Http\Controllers\VersionRestoreController;
use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\Article;
use Arqel\Versioning\Tests\Fixtures\ArticleResource;
use Arqel\Versioning\Tests\Fixtures\DenyViewPolicy;
use Arqel\Versioning\Tests\Fixtures\PlainWidget;
use Arqel\Versioning\Tests\Fixtures\PlainWidgetResource;
use Arqel\Versioning\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function authedUserForVersioningLocale(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test User', 'email' => 't@e.dev']);

    return $user;
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    app()->setLocale('pt_BR');
});

afterEach(function (): void {
    app()->setLocale('en');
});

it('localizes the registry-not-bound 404 message (history)', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'V1', 'body' => 'b', 'status' => 'draft']);

    app()->offsetUnset(ResourceRegistry::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->getJson("/admin/articles/{$article->id}/versions");

    $response->assertStatus(404)
        ->assertJsonPath('message', 'ResourceRegistry não está registrado');
});

it('localizes the forbidden 403 message (history)', function (): void {
    Gate::policy(Article::class, DenyViewPolicy::class);

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'Secret', 'body' => 'leak', 'status' => 'draft']);

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->getJson("/admin/articles/{$article->id}/versions");

    $response->assertStatus(403)
        ->assertJsonPath('message', 'Acesso negado');
});

it('localizes the not-versionable 422 message (restore)', function (): void {
    if (! Schema::hasTable('plain_widgets')) {
        Schema::create('plain_widgets', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    Route::post(
        '/admin/{resource}/{id}/versions/{versionId}/restore',
        VersionRestoreController::class,
    )->name('arqel.versioning.restore.locale');

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(PlainWidgetResource::class);

    $row = PlainWidget::create(['name' => 'x']);

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->postJson(route('arqel.versioning.restore.locale', [
            'resource' => 'plain-widgets',
            'id' => $row->getKey(),
            'versionId' => 1,
        ]));

    $response->assertStatus(422)
        ->assertJsonPath('message', 'O model não usa a trait Versionable.');
});

it('localizes the version-not-found 404 message (restore)', function (): void {
    Route::post(
        '/admin/{resource}/{id}/versions/{versionId}/restore',
        VersionRestoreController::class,
    )->name('arqel.versioning.restore.notfound');

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $a = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);
    $b = Article::create(['title' => 'B', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $bVersion */
    $bVersion = $b->versions()->first();

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->postJson(route('arqel.versioning.restore.notfound', [
            'resource' => 'articles',
            'id' => $a->getKey(),
            'versionId' => $bVersion->id,
        ]));

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Versão não encontrada para o registro.');
});

it('localizes the resource-not-registered 404 message with the slug (history)', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->getJson('/admin/unknown-slug/1/versions');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Recurso [unknown-slug] não está registrado');
});

it('localizes the resource-invalid 404 message with the slug (history)', function (): void {
    // A registry whose findBySlug resolves to a class that lacks getModel()
    // — exercises the defensive resource_invalid branch the real registry
    // can't reach (every Resource extends the abstract base with getModel()).
    $fakeRegistry = new class
    {
        public function findBySlug(string $slug): string
        {
            return stdClass::class;
        }
    };
    app()->instance(ResourceRegistry::class, $fakeRegistry);

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->getJson('/admin/broken/1/versions');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Recurso [broken] é inválido');
});

it('localizes the resource-not-found 404 message with the slug (restore)', function (): void {
    Route::post(
        '/admin/{resource}/{id}/versions/{versionId}/restore',
        VersionRestoreController::class,
    )->name('arqel.versioning.restore.unknownslug');

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(ArticleResource::class);

    $article = Article::create(['title' => 'A', 'body' => 'b', 'status' => 'draft']);

    /** @var Version $version */
    $version = $article->versions()->first();

    /** @var TestCase $this */
    $response = $this->actingAs(authedUserForVersioningLocale())
        ->postJson(route('arqel.versioning.restore.unknownslug', [
            'resource' => 'unknown-slug',
            'id' => $article->getKey(),
            'versionId' => $version->id,
        ]));

    $response->assertStatus(404)
        ->assertJsonPath('message', "Recurso 'unknown-slug' não encontrado.");
});
