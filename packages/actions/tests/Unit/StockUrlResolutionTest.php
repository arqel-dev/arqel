<?php

declare(strict_types=1);

use Arqel\Actions\Actions;

final class StubResourceWithSlug
{
    public static ?string $slug = 'posts';
}

final class StubRecord
{
    public function __construct(public int $id = 7) {}

    public function getKey(): int
    {
        return $this->id;
    }
}

it('Actions::edit() resolves to GET /admin/{slug}/{id}/edit when given resource + record', function (): void {
    $resource = new StubResourceWithSlug;
    $record = new StubRecord(7);

    $array = Actions::edit()->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/7/edit')
        ->and($array['method'])->toBe('GET');
});

it('Actions::delete() resolves to DELETE /admin/{slug}/{id}', function (): void {
    $resource = new StubResourceWithSlug;
    $record = new StubRecord(42);

    $array = Actions::delete()->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/42')
        ->and($array['method'])->toBe('DELETE');
});

it('Actions::view() resolves to GET /admin/{slug}/{id}', function (): void {
    $resource = new StubResourceWithSlug;
    $record = new StubRecord(13);

    $array = Actions::view()->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/13')
        ->and($array['method'])->toBe('GET');
});

it('Actions::restore() resolves to POST /admin/{slug}/{id}/restore', function (): void {
    $resource = new StubResourceWithSlug;
    $record = new StubRecord(99);

    $array = Actions::restore()->toArray(null, $record, $resource);

    expect($array['url'])->toBe('/admin/posts/99/restore')
        ->and($array['method'])->toBe('POST');
});

it('Actions::deleteBulk() resolves to POST /admin/{slug}/bulk/delete with no record', function (): void {
    $resource = new StubResourceWithSlug;

    $array = Actions::deleteBulk()->toArray(null, null, $resource);

    expect($array['url'])->toBe('/admin/posts/bulk/delete')
        ->and($array['method'])->toBe('POST');
});

it('Actions::edit()->toArray() without args remains backwards-compatible (no url key)', function (): void {
    $array = Actions::edit()->toArray();

    expect($array)->not->toHaveKey('url');
});
