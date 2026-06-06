<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Collab\AwarenessChannelAuthorizer;
use Arqel\Realtime\Events\YjsUpdateReceived;
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

/**
 * Resource mapping the slug `posts` to {@see CollabAuthorizerFakePost}, so the
 * authorizer can resolve the same slug-keyed channel the REST endpoint
 * broadcasts on.
 */
final class CollabAuthorizerFakePostResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = CollabAuthorizerFakePost::class;

    public static ?string $slug = 'posts';

    public function fields(): array
    {
        return [];
    }
}

/**
 * Policy that always denies `view` — registered via Gate::policy() with no
 * named Gate, exercising the path that Gate::has() cannot see.
 */
final class CollabAuthorizerDenyingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return false;
    }
}

/**
 * Policy that always allows `view`.
 */
final class CollabAuthorizerAllowingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return true;
    }
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

it('denies access when a Policy denies view and no named Gate exists', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Secret']);

    Gate::policy(CollabAuthorizerFakePost::class, CollabAuthorizerDenyingPolicy::class);

    // No named Gate is registered — Gate::has() cannot see the Policy.
    expect(Gate::has('view'))->toBeFalse();

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        $post->getKey(),
        'body',
    ))->toBeFalse();
});

it('allows access when a Policy allows view and no named Gate exists', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Shared']);

    Gate::policy(CollabAuthorizerFakePost::class, CollabAuthorizerAllowingPolicy::class);

    expect(Gate::has('view'))->toBeFalse();

    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        CollabAuthorizerFakePost::class,
        $post->getKey(),
        'body',
    ))->toBeTrue();
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

it('resolves the model from a Resource slug via the registry and authorizes', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Slug-keyed']);
    /** @var int $postId */
    $postId = $post->getKey();

    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(CollabAuthorizerFakePostResource::class);

    Gate::define('view', static fn ($user, $record): bool => true);

    $authorizer = new AwarenessChannelAuthorizer;

    // `posts` is the slug the REST controller persists and broadcasts on —
    // not an FQCN — so before the fix the authorizer resolved null and denied.
    expect($authorizer->authorize(
        authedCollabAuthUser(),
        'posts',
        $postId,
        'body',
    ))->toBeTrue();
});

it('authorizes the very channel YjsUpdateReceived broadcasts to (cross-half consistency)', function (): void {
    $post = CollabAuthorizerFakePost::query()->create(['title' => 'Synced']);
    /** @var int $postId */
    $postId = $post->getKey();

    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(CollabAuthorizerFakePostResource::class);

    Gate::define('view', static fn ($user, $record): bool => true);

    // The REST `store()` dispatches the event keyed by the Resource slug.
    $slug = CollabAuthorizerFakePostResource::getSlug();
    $event = new YjsUpdateReceived($slug, $postId, 'body', base64_encode('y'), 1, 7);

    [$channel] = $event->broadcastOn();
    $expectedChannel = "arqel.collab.{$slug}.{$postId}.body";

    // Pin the broadcast channel to the slug-keyed name…
    expect($channel->name)->toBe("private-{$expectedChannel}");

    // …and confirm the authorizer accepts that exact (modelType, id, field).
    $authorizer = new AwarenessChannelAuthorizer;

    expect($authorizer->authorize(
        authedCollabAuthUser(),
        $slug,
        $postId,
        'body',
    ))->toBeTrue();
});
