<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Tests\Fixtures\FakePresenceUser;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Fake Eloquent model so the presence callback can resolve a record via the
 * registry and exercise the Policy-hardening path (issue #239).
 */
final class PresenceFakePost extends Model
{
    public $timestamps = false;

    protected $table = 'presence_fake_posts';

    protected $guarded = [];
}

/**
 * Resource mapping the slug `posts` to {@see PresenceFakePost}.
 */
final class PresenceFakePostResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = PresenceFakePost::class;

    public static ?string $slug = 'posts';

    public function fields(): array
    {
        return [];
    }
}

/**
 * Policy that always denies `view` (registered via Gate::policy() with no
 * named Gate — the path Gate::has() cannot see).
 */
final class PresenceDenyingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return false;
    }
}

/**
 * Policy that always allows `view`.
 */
final class PresenceAllowingPolicy
{
    public function view(mixed $user, mixed $record): bool
    {
        return true;
    }
}

/**
 * Create a {@see PresenceFakePost} and return its primary key as a string
 * (the presence channel binds `{recordId}` as a string).
 */
function presenceFakePostId(): string
{
    if (! Schema::hasTable('presence_fake_posts')) {
        Schema::create('presence_fake_posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
    }

    $post = PresenceFakePost::query()->create(['title' => 'Hello']);
    /** @var int $id */
    $id = $post->getKey();

    return (string) $id;
}

function bindPresenceRegistry(): void
{
    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(PresenceFakePostResource::class);
}

/**
 * Invoke the presence channel callback that the package registers via
 * `routes/channels.php`. Returns the raw payload (array | false | null)
 * the channel closure produced for the given user + parameters.
 *
 * @return array{id: int|string|null, name: string|null, avatar: string|null}|false|null
 */
function invokePresenceChannel(
    string $pattern,
    FakePresenceUser $user,
    string $resource,
    string $recordId,
): array|false|null {
    /** @var Broadcaster $broadcaster */
    $broadcaster = Broadcast::driver();

    /** @var array<string, callable> $channels */
    $channels = $broadcaster->getChannels();

    expect($channels)->toHaveKey($pattern);

    $callback = $channels[$pattern];
    expect(is_callable($callback))->toBeTrue();

    /** @var array{id: int|string|null, name: string|null, avatar: string|null}|false|null $result */
    $result = $callback($user, $resource, $recordId);

    return $result;
}

it('registers the arqel.presence.{resource}.{recordId} channel', function (): void {
    /** @var Broadcaster $broadcaster */
    $broadcaster = Broadcast::driver();

    expect($broadcaster->getChannels())
        ->toHaveKey('arqel.presence.{resource}.{recordId}');
});

it('returns the user payload in scaffold mode (registry unbound, no Gate)', function (): void {
    // Registry is unbound here (no bindPresenceRegistry()) → realtime standalone,
    // no core/Policy possible → scaffold-open is the intended behavior (Option B).
    expect(app()->bound('Arqel\\Core\\Resources\\ResourceRegistry'))->toBeFalse();

    $user = new FakePresenceUser(id: 99, name: 'Grace Hopper', avatar_url: 'https://x.test/g.png');

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        $user,
        'posts',
        '42',
    );

    expect($payload)->toBe([
        'id' => 99,
        'name' => 'Grace Hopper',
        'avatar' => 'https://x.test/g.png',
    ]);
});

it('returns false when the view-resource-presence Gate denies', function (): void {
    Gate::define('view-resource-presence', static fn (): bool => false);

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser,
        'posts',
        '42',
    );

    expect($payload)->toBeFalse();
});

it('returns the payload when the view-resource-presence Gate allows', function (): void {
    Gate::define(
        'view-resource-presence',
        static fn (FakePresenceUser $u, string $resource, string $recordId): bool => $resource === 'posts' && $recordId === '42',
    );

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 7, name: 'Ada', avatar_url: null),
        'posts',
        '42',
    );

    expect($payload)->toMatchArray([
        'id' => 7,
        'name' => 'Ada',
        'avatar' => null,
    ]);
});

it('denies presence when a Policy denies view and no named Gate exists (issue #239)', function (): void {
    $recordId = presenceFakePostId();
    bindPresenceRegistry();

    Gate::policy(PresenceFakePost::class, PresenceDenyingPolicy::class);

    // No `view-resource-presence` Gate — Gate::has() cannot see the Policy. Pre-fix
    // the `&&` short-circuited and the member-info array leaked (the #239 bug).
    expect(Gate::has('view-resource-presence'))->toBeFalse();

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 5),
        'posts',
        $recordId,
    );

    expect($payload)->toBeFalse();
});

it('returns the payload when a Policy allows view and no named Gate exists', function (): void {
    $recordId = presenceFakePostId();
    bindPresenceRegistry();

    Gate::policy(PresenceFakePost::class, PresenceAllowingPolicy::class);

    expect(Gate::has('view-resource-presence'))->toBeFalse();

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 13, name: 'Linus', avatar_url: null),
        'posts',
        $recordId,
    );

    expect($payload)->toMatchArray([
        'id' => 13,
        'name' => 'Linus',
        'avatar' => null,
    ]);
});

it('stays open for a resolvable record with no Policy and no view Gate (scaffold)', function (): void {
    $recordId = presenceFakePostId();
    bindPresenceRegistry();

    // Registry bound + record resolves, but neither a `view` Gate nor a Policy
    // exists → genuine scaffold → open (Option B).
    expect(Gate::has('view-resource-presence'))->toBeFalse();
    expect(Gate::has('view'))->toBeFalse();

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 21, name: 'Scaffold', avatar_url: null),
        'posts',
        $recordId,
    );

    expect($payload)->toMatchArray([
        'id' => 21,
        'name' => 'Scaffold',
        'avatar' => null,
    ]);
});

it('denies when the registry is bound but the slug does not resolve (Option B)', function (): void {
    bindPresenceRegistry();

    // Registry bound (real Arqel app) but unknown slug → record === null → deny.
    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 1),
        'nonexistent-slug',
        '1',
    );

    expect($payload)->toBeFalse();
});
