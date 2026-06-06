<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Collab\YjsDocument;
use Arqel\Realtime\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

/**
 * Real Eloquent model the collab REST endpoints resolve from the
 * `posts` slug for record-level authorization. With no Gate/Policy
 * registered the controller runs in scaffold mode (allow), matching
 * the awareness-channel authorizer's open-by-default behaviour.
 */
final class CollabControllerFakePost extends Model
{
    public $timestamps = false;

    protected $table = 'collab_controller_fake_posts';

    protected $guarded = [];
}

final class CollabControllerFakePostResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = CollabControllerFakePost::class;

    public static ?string $slug = 'posts';

    public function fields(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('collab_controller_fake_posts')) {
        Schema::create('collab_controller_fake_posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
    }

    // Seed record #42 so the controller resolves `posts/42` and the
    // existing snapshot scenarios keep exercising the happy path.
    CollabControllerFakePost::query()->firstOrCreate(['id' => 42], ['title' => 'Post 42']);

    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(CollabControllerFakePostResource::class);
});

function authedCollabUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 7, 'name' => 'Editor', 'email' => 'e@x.dev']);

    return $user;
}

it('returns empty state when document does not exist', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->getJson('/admin/posts/42/collab/body');

    $response->assertOk();
    expect($response->json())->toBe([
        'state' => null,
        'version' => 0,
    ]);
});

it('returns persisted state encoded as base64', function (): void {
    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => 42,
        'field' => 'body',
        'state' => "\x01\x02\x03binary",
        'version' => 3,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->getJson('/admin/posts/42/collab/body');

    $response->assertOk();
    /** @var array{state: string, version: int} $body */
    $body = (array) $response->json();
    expect($body['version'])->toBe(3)
        ->and(base64_decode($body['state'], true))->toBe("\x01\x02\x03binary");
});

it('creates a new document on POST and returns version 1', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('hello'),
            'version' => 0,
        ]);

    $response->assertStatus(201);
    expect($response->json('version'))->toBe(1);

    /** @var YjsDocument $document */
    $document = YjsDocument::query()->firstOrFail();
    expect($document->state)->toBe('hello')
        ->and($document->last_user_id)->toBe(7);
});

it('updates the document and increments version when incoming.version >= current', function (): void {
    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => 42,
        'field' => 'body',
        'state' => 'old',
        'version' => 5,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('new'),
            'version' => 5,
        ]);

    $response->assertOk();
    expect($response->json('version'))->toBe(6);

    $document = YjsDocument::query()->firstOrFail();
    expect($document->state)->toBe('new');
});

it('returns 409 conflict when incoming version is older than server', function (): void {
    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => 42,
        'field' => 'body',
        'state' => 'current',
        'version' => 10,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('stale'),
            'version' => 3,
        ]);

    $response->assertStatus(409);
    expect($response->json('serverVersion'))->toBe(10);
});

it('returns 401 (or redirects) when unauthenticated', function (): void {
    /** @var TestCase $this */
    $response = $this->getJson('/admin/posts/42/collab/body');

    expect(in_array($response->status(), [401, 403, 302], true))->toBeTrue();
});

it('returns 422 when state is missing or invalid base64', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs(authedCollabUser())
        ->postJson('/admin/posts/42/collab/body', ['state' => '', 'version' => 0]);

    $response->assertStatus(422);
});
