<?php

declare(strict_types=1);

use Arqel\Realtime\Channels\ResourceChannelAuthorizer;
use Arqel\Realtime\Tests\Fixtures\FakePresenceUser;
use Arqel\Realtime\Tests\Fixtures\FakeResourceRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Stub `ResourceRegistry`-shaped object — the authorizer resolves it via
 * the FQCN string `Arqel\\Core\\Resources\\ResourceRegistry`. We bind a
 * stub to that name in the container per-test, exercising both the bound
 * and unbound code paths without pulling `arqel/core` into the test deps.
 */
final class FakeResourceRegistry
{
    /** @var array<string, class-string> */
    public array $map = [];

    public function findBySlug(string $slug): ?string
    {
        return $this->map[$slug] ?? null;
    }
}

/**
 * Lightweight Resource shim that satisfies the contract used by the
 * authorizer (`getModel(): string`).
 */
final class FakeAuthResource
{
    public static string $modelClass = FakeResourceRecord::class;

    public static function getModel(): string
    {
        return self::$modelClass;
    }
}

beforeEach(function (): void {
    // Reset the container binding between tests.
    app()->forgetInstance('Arqel\\Core\\Resources\\ResourceRegistry');
});

/**
 * @param  class-string  $resourceClass
 */
function bindFakeRegistry(string $slug = 'posts', string $resourceClass = FakeAuthResource::class): void
{
    $registry = new FakeResourceRegistry;
    $registry->map[$slug] = $resourceClass;
    app()->instance('Arqel\\Core\\Resources\\ResourceRegistry', $registry);
}

it('authorizeResource returns true when Gate viewAny allows', function (): void {
    bindFakeRegistry();
    Gate::define('viewAny', static fn (): bool => true);

    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))->toBeTrue();
});

it('authorizeResource returns false when Gate viewAny denies', function (): void {
    bindFakeRegistry();
    Gate::define('viewAny', static fn (): bool => false);

    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))->toBeFalse();
});

it('authorizeResource returns false when ResourceRegistry is not bound', function (): void {
    expect(app()->bound('Arqel\\Core\\Resources\\ResourceRegistry'))->toBeFalse();

    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))->toBeFalse();
});

it('authorizeResource returns false when slug does not resolve to a Resource', function (): void {
    bindFakeRegistry(slug: 'comments');

    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))->toBeFalse();
});

it('authorizeRecord returns true when record exists and Gate view allows', function (): void {
    bindFakeRegistry();
    $record = FakeResourceRecord::create(['name' => 'hello']);
    Gate::define('view', static fn (): bool => true);

    /** @var int|string $recordId */
    $recordId = $record->getKey();

    expect(ResourceChannelAuthorizer::authorizeRecord(new FakePresenceUser, 'posts', $recordId))
        ->toBeTrue();
});

it('authorizeRecord returns false when Model::find returns null', function (): void {
    bindFakeRegistry();
    Gate::define('view', static fn (): bool => true);

    expect(ResourceChannelAuthorizer::authorizeRecord(new FakePresenceUser, 'posts', 999_999))
        ->toBeFalse();
});

it('authorizeRecord returns false when Gate view denies', function (): void {
    bindFakeRegistry();
    $record = FakeResourceRecord::create(['name' => 'denied']);
    Gate::define('view', static fn (): bool => false);

    /** @var int|string $recordId */
    $recordId = $record->getKey();

    expect(ResourceChannelAuthorizer::authorizeRecord(new FakePresenceUser, 'posts', $recordId))
        ->toBeFalse();
});

it('authorizeActionJob returns true when cache owner equals auth identifier', function (): void {
    Cache::put('arqel.action.job-abc.user', 42);

    expect(ResourceChannelAuthorizer::authorizeActionJob(new FakePresenceUser(id: 42), 'job-abc'))
        ->toBeTrue();
});

it('authorizeActionJob returns false when cache owner differs', function (): void {
    Cache::put('arqel.action.job-xyz.user', 99);

    expect(ResourceChannelAuthorizer::authorizeActionJob(new FakePresenceUser(id: 42), 'job-xyz'))
        ->toBeFalse();
});

it('authorizeActionJob returns false on cache miss', function (): void {
    expect(ResourceChannelAuthorizer::authorizeActionJob(new FakePresenceUser(id: 42), 'job-missing'))
        ->toBeFalse();
});
