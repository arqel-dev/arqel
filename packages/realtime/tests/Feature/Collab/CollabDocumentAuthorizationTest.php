<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Collab\YjsDocument;
use Arqel\Realtime\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Real Eloquent model the collab REST endpoints resolve from the
 * `{resource}` slug, so the controller can run record-level authz.
 */
final class CollabRestFakePost extends Model
{
    public $timestamps = false;

    protected $table = 'collab_rest_fake_posts';

    protected $guarded = [];
}

/**
 * Resource fixture mapping the `posts` slug to {@see CollabRestFakePost}.
 */
final class CollabRestFakePostResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = CollabRestFakePost::class;

    public static ?string $slug = 'posts';

    public function fields(): array
    {
        return [];
    }
}

final class CollabRestDenyingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return false;
    }

    public function update(mixed $user, mixed $record): bool
    {
        return false;
    }
}

final class CollabRestAllowingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return true;
    }

    public function update(mixed $user, mixed $record): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('collab_rest_fake_posts')) {
        Schema::create('collab_rest_fake_posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
    }

    // Bind a ResourceRegistry that maps the `posts` slug to a real model so
    // the controller can resolve the owning record for authorization. The
    // realtime test app only loads RealtimeServiceProvider, so bind the
    // registry as a singleton ourselves to keep registrations stable.
    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(CollabRestFakePostResource::class);
});

function authedAuthzUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 5, 'name' => 'Editor', 'email' => 'e@x.dev']);

    return $user;
}

it('forbids show (GET) when the view Policy denies the record', function (): void {
    $post = CollabRestFakePost::query()->create(['title' => 'Secret']);
    /** @var int $postId */
    $postId = $post->getKey();

    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => $postId,
        'field' => 'body',
        'state' => "\x01\x02secret",
        'version' => 4,
        'last_user_id' => 99,
        'updated_at' => now(),
    ]);

    Gate::policy(CollabRestFakePost::class, CollabRestDenyingPolicy::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->getJson("/admin/posts/{$postId}/collab/body");

    $response->assertForbidden();
    // The snapshot must NOT leak.
    expect($response->json('state'))->toBeNull();
});

it('allows show (GET) when the view Policy allows the record', function (): void {
    $post = CollabRestFakePost::query()->create(['title' => 'Shared']);
    /** @var int $postId */
    $postId = $post->getKey();

    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => $postId,
        'field' => 'body',
        'state' => "\x01\x02shared",
        'version' => 2,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    Gate::policy(CollabRestFakePost::class, CollabRestAllowingPolicy::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->getJson("/admin/posts/{$postId}/collab/body");

    $response->assertOk();
    /** @var string $state */
    $state = $response->json('state');
    expect(base64_decode($state, true))->toBe("\x01\x02shared");
});

it('forbids store (POST) when the update Policy denies the record', function (): void {
    $post = CollabRestFakePost::query()->create(['title' => 'Locked']);
    /** @var int $postId */
    $postId = $post->getKey();

    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => $postId,
        'field' => 'body',
        'state' => 'original',
        'version' => 3,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    Gate::policy(CollabRestFakePost::class, CollabRestDenyingPolicy::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->postJson("/admin/posts/{$postId}/collab/body", [
            'state' => base64_encode('overwritten'),
            'version' => 3,
        ]);

    $response->assertForbidden();

    // The stored snapshot must remain untouched.
    $document = YjsDocument::query()->firstOrFail();
    expect($document->state)->toBe('original')
        ->and($document->version)->toBe(3);
});

it('allows store (POST) when the update Policy allows the record', function (): void {
    $post = CollabRestFakePost::query()->create(['title' => 'Editable']);
    /** @var int $postId */
    $postId = $post->getKey();

    Gate::policy(CollabRestFakePost::class, CollabRestAllowingPolicy::class);

    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->postJson("/admin/posts/{$postId}/collab/body", [
            'state' => base64_encode('fresh'),
            'version' => 0,
        ]);

    $response->assertStatus(201);

    $document = YjsDocument::query()->firstOrFail();
    expect($document->state)->toBe('fresh');
});

it('returns 404 on show when the resource/id cannot be resolved to a record', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->getJson('/admin/posts/9999999/collab/body');

    $response->assertNotFound();
});

it('returns 404 on show when the resource slug is unknown', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->getJson('/admin/unknown-resource/1/collab/body');

    $response->assertNotFound();
});

it('returns 404 on store when the resource/id cannot be resolved to a record', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedAuthzUser())
        ->postJson('/admin/posts/9999999/collab/body', [
            'state' => base64_encode('x'),
            'version' => 0,
        ]);

    $response->assertNotFound();
});
