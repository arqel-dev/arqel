<?php

declare(strict_types=1);

use Arqel\Actions\Types\HeaderAction;
use Arqel\Actions\Types\RowAction;
use Arqel\Actions\Types\ToolbarAction;

/**
 * #231 (endpoint half): a custom action that runs a server-side
 * `->action(Closure)` and declares NO explicit `->url()` had no working
 * HTTP path. `resolveStockUrl()` only covered the stock verbs
 * (view/edit/delete/restore) and bulk, so a custom callback action
 * emitted NO `url` from `toArray()` — and the React fallback then POSTed
 * to the dead `/arqel-dev/actions/{name}` route removed in #174 → 404.
 *
 * The fix makes a custom callback action serialise a conventional URL
 * pointing at core's authorised action-dispatch endpoint, mirroring the
 * bulk fix (#48): `POST /admin/{slug}/actions/{name}[/{id}]`.
 */
final class CustomEndpointStubResource
{
    public static ?string $slug = 'posts';
}

final class CustomEndpointStubRecord
{
    public function __construct(public int $id = 7) {}

    public function getKey(): int
    {
        return $this->id;
    }
}

it('a custom row action with a callback emits POST /admin/{slug}/actions/{name}/{id} (#231)', function (): void {
    $resource = new CustomEndpointStubResource;
    $record = new CustomEndpointStubRecord(7);

    $array = RowAction::make('publish')
        ->action(fn ($record) => $record)
        ->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/actions/publish/7')
        ->and($array['method'])->toBe('POST');
});

it('a custom row action emits the {id} placeholder at table level (no record) (#231)', function (): void {
    $resource = new CustomEndpointStubResource;

    $array = RowAction::make('publish')
        ->action(fn ($record) => $record)
        ->toArray(null, null, $resource);

    expect($array['url'])->toBe('/admin/posts/actions/publish/{id}')
        ->and($array['method'])->toBe('POST');
});

it('a custom toolbar action with a callback emits a record-less endpoint URL (#231)', function (): void {
    $resource = new CustomEndpointStubResource;

    $array = ToolbarAction::make('import')
        ->action(fn () => null)
        ->toArray(null, null, $resource);

    expect($array['url'])->toBe('/admin/posts/actions/import')
        ->and($array['method'])->toBe('POST');
});

it('a custom header action with a callback emits a per-record endpoint URL (#231)', function (): void {
    $resource = new CustomEndpointStubResource;
    $record = new CustomEndpointStubRecord(42);

    $array = HeaderAction::make('archive')
        ->action(fn ($record) => $record)
        ->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/actions/archive/42')
        ->and($array['method'])->toBe('POST');
});

it('an explicit ->url() still wins over the custom endpoint URL (#231)', function (): void {
    $resource = new CustomEndpointStubResource;
    $record = new CustomEndpointStubRecord(7);

    $array = RowAction::make('open')
        ->url('/somewhere', 'GET')
        ->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/somewhere')
        ->and($array['method'])->toBe('GET');
});

it('a custom callback action without a resource still emits no url (table has no slug context)', function (): void {
    // Backwards-compatible: without a $resource the action cannot know the
    // panel/slug, so no stock url is emitted (the existing table-level
    // serialisation contract for the no-resource call).
    $array = RowAction::make('publish')->action(fn () => null)->toArray();

    expect($array)->not->toHaveKey('url');
});
