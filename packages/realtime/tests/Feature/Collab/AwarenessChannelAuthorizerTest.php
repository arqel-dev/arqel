<?php

declare(strict_types=1);

use Arqel\Realtime\Collab\AwarenessChannelAuthorizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Fake Eloquent model so the authorizer can resolve a record by FQCN.
 */
final class CollabAuthorizerFakePost extends Model
{
    public $timestamps = false;

    protected $table = 'collab_authorizer_fake_posts';

    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('collab_authorizer_fake_posts')) {
        Schema::create('collab_authorizer_fake_posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
    }
});

function authedCollabAuthUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 11, 'name' => 'Editor', 'email' => 'e@x.dev']);

    return $user;
}

it('allows access when Gate view allows the record', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Hello']);

    Gate::define('view', static fn ($user, $record): bool => true);

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        $post->getKey(),
        'body',
    ))->toBeTrue();
});

it('denies access when Gate view denies the record', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Hi']);

    Gate::define('view', static fn ($user, $record): bool => false);

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        $post->getKey(),
        'body',
    ))->toBeFalse();
});

it('denies when registry is unbound and modelType is not a real FQCN', function (): void {
    expect(app()->bound('Arqel\\Core\\Resources\\ResourceRegistry'))->toBeFalse();

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        'posts',
        42,
        'body',
    ))->toBeFalse();
});

it('denies when the record does not exist', function (): void {
    Gate::define('view', static fn ($user, $record): bool => true);

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        9999999,
        'body',
    ))->toBeFalse();
});

it('allows access when no view Gate is registered (consistent with presence pattern)', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'No gate']);

    expect(Gate::has('view'))->toBeFalse();

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        $post->getKey(),
        'body',
    ))->toBeTrue();
});
