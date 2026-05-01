<?php

declare(strict_types=1);

use Arqel\Realtime\Channels\ResourceChannelAuthorizer;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;
use Arqel\Realtime\Events\ResourceUpdated;
use Arqel\Realtime\Presence\PresenceChannelResolver;
use Arqel\Realtime\Tests\Fixtures\FakePostResource;
use Arqel\Realtime\Tests\Fixtures\FakePresenceUser;
use Arqel\Realtime\Tests\Fixtures\FakeResourceRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Cobertura de gaps identificados por RT-011 — exercita os caminhos
 * defensivos das classes do `arqel/realtime` que ainda não tinham
 * teste explícito (slug pluralization edge cases, payload com chave
 * string, dispatch sem dirty diff, registry sem `findBySlug`, pattern
 * de presence com placeholders custom, etc).
 *
 * Estes testes são complementares aos suites por classe — ficam
 * agrupados em `Unit/Coverage/` para deixar claro que são "filling
 * the gaps" e não a especificação canónica do componente.
 */

/**
 * Resource shim com getSlug() que retorna string vazia — força
 * o fallback do basename mesmo sem throw.
 */
final class EmptySlugResource
{
    public static function getSlug(): string
    {
        return '';
    }
}

/**
 * Resource shim com nome que precisa pluralização real (-y → -ies)
 * para validar que o algoritmo do `Str::plural()` é aplicado.
 */
final class CategoryResource
{
    public static function getSlug(): string
    {
        throw new RuntimeException('force fallback');
    }
}

/**
 * Modelo com chave primária string (slug-style) para exercitar o ramo
 * `int|string $id` do `broadcastWith()` e do canal per-record.
 */
final class FakeStringKeyRecord extends Model
{
    protected $table = 'fake_resource_records';

    protected $guarded = [];

    public $timestamps = false;

    /** @var string */
    protected $keyType = 'string';

    public $incrementing = false;
}

/**
 * Trait host que sempre devolve `id` string em `auth()->id()`, para
 * exercitar o coerce defensivo em `resolveBroadcastingUserId()`.
 */
final class FakeStringAuthResource
{
    use BroadcastsResourceUpdates;

    public static function getSlug(): string
    {
        return 'string-auth';
    }

    public function runUpdate(Model $record): void
    {
        $this->afterUpdate($record);
    }
}

/**
 * Registry stub sem `findBySlug()` — cobre o ramo `method_exists`
 * em `ResourceChannelAuthorizer::resolveResourceClass()`.
 */
final class RegistryWithoutFindBySlug
{
    public string $unrelated = 'noop';
}

/**
 * Registry mapeando slug → resource class que NÃO expõe `getModel()`,
 * forçando o catch-all `Throwable` do authorizer (Error: undefined method).
 */
final class RegistryWithBrokenResource
{
    public function findBySlug(string $slug): string
    {
        return BrokenResourceWithoutGetModel::class;
    }
}

final class BrokenResourceWithoutGetModel
{
    // sem getModel() — chamada estática vai disparar Error.
}

it('broadcasts the per-record channel when getKey returns a string', function (): void {
    $record = new FakeStringKeyRecord(['name' => 'string-key']);
    $record->setAttribute('id', 'sluggy-id-7');
    $record->exists = true;

    $event = new ResourceUpdated(
        resourceClass: FakePostResource::class,
        record: $record,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2)
        ->and($channels[1]->name)->toBe('private-arqel.posts.sluggy-id-7');

    $payload = $event->broadcastWith();

    expect($payload['id'])->toBe('sluggy-id-7');
});

it('falls back when getSlug returns an empty string', function (): void {
    $record = FakeResourceRecord::create(['name' => 'empty-slug']);

    $event = new ResourceUpdated(
        resourceClass: EmptySlugResource::class,
        record: $record,
    );

    // EmptySlug -> beforeLast('Resource') -> 'EmptySlug' -> 'empty-slug' -> plural
    expect($event->broadcastOn()[0]->name)->toBe('private-arqel.empty-slugs');
});

it('pluralises basename slugs through Str::plural (Category -> categories)', function (): void {
    $record = FakeResourceRecord::create(['name' => 'category']);

    $event = new ResourceUpdated(
        resourceClass: CategoryResource::class,
        record: $record,
    );

    expect($event->broadcastOn()[0]->name)->toBe('private-arqel.categories');
});

it('dispatches even when the record has no dirty changes', function (): void {
    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'pristine']);
    // Não chamamos save/touch — afterUpdate dispara independente de dirty.

    expect($record->isDirty())->toBeFalse();

    (new FakeStringAuthResource)->runUpdate($record);

    Event::assertDispatched(ResourceUpdated::class);
});

it('coerces non-int auth ids to null in the broadcaster user resolver', function (): void {
    Event::fake([ResourceUpdated::class]);

    Auth::shouldReceive('id')->andReturn('uuid-not-int');

    $record = FakeResourceRecord::create(['name' => 'string-auth']);
    (new FakeStringAuthResource)->runUpdate($record);

    Event::assertDispatched(
        ResourceUpdated::class,
        fn (ResourceUpdated $event): bool => $event->updatedByUserId === null,
    );
});

it('substitutes only the documented placeholders, leaving extras intact', function (): void {
    config()->set(
        'arqel-realtime.presence.channel_pattern',
        'arqel.{tenant}.{resource}-{recordId}-presence',
    );

    // {tenant} fica literal — só {resource} e {recordId} são substituídos.
    expect(PresenceChannelResolver::forResource('orders', 'abc'))
        ->toBe('arqel.{tenant}.orders-abc-presence');
});

it('falls back to default pattern when config returns a non-string', function (): void {
    // Array no slot do pattern — a guarda `is_string` força o fallback.
    config()->set('arqel-realtime.presence.channel_pattern', ['unexpected', 'shape']);

    expect(PresenceChannelResolver::forResource('posts', 1))
        ->toBe('arqel.presence.posts.1');
});

it('authorizeResource returns false when registry has no findBySlug method', function (): void {
    app()->instance('Arqel\\Core\\Resources\\ResourceRegistry', new RegistryWithoutFindBySlug);

    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))
        ->toBeFalse();
});

it('authorizeResource swallows resource-resolution errors and denies', function (): void {
    app()->instance('Arqel\\Core\\Resources\\ResourceRegistry', new RegistryWithBrokenResource);

    // BrokenResourceWithoutGetModel::getModel() não existe → Error capturado.
    expect(ResourceChannelAuthorizer::authorizeResource(new FakePresenceUser, 'posts'))
        ->toBeFalse();
});

it('authorizeRecord returns false when ResourceRegistry is not bound', function (): void {
    app()->forgetInstance('Arqel\\Core\\Resources\\ResourceRegistry');

    Gate::define('view', static fn (): bool => true);

    expect(ResourceChannelAuthorizer::authorizeRecord(new FakePresenceUser, 'posts', 1))
        ->toBeFalse();
});

it('authorizeActionJob denies when stored owner type differs from auth identifier', function (): void {
    // Cache grava string "42", user retorna int 42 — comparação estrita reprova.
    Cache::put('arqel.action.type-mismatch.user', '42');

    expect(ResourceChannelAuthorizer::authorizeActionJob(new FakePresenceUser(id: 42), 'type-mismatch'))
        ->toBeFalse();
});
