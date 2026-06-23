<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Versioning\Http\Controllers\VersionRestoreController;
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
