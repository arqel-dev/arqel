<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\SoftStub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * #244: restoring a soft-deleted record was impossible. Two gaps:
 *
 *  1. `ResourceController::findOrFail` ran the default query, so the
 *     SoftDeletes global scope hid trashed rows → null → 404 when loading a
 *     deleted record for restore.
 *  2. `Actions::restore()` serialised `POST {slug}/{id}/restore` but no such
 *     route was registered → 404.
 *
 * These specs drive the new `restore` route + controller method + the
 * trashed-aware load (`findWithTrashedOrFail`).
 */
final class RestoreSoftStubResource extends Resource
{
    public static string $model = SoftStub::class;

    public static ?string $slug = 'soft-restore';

    public function fields(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RestoreSoftStubResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('soft_stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    SoftStub::query()->create(['id' => 1, 'name' => 'Alice']);
});

it('registers the arqel.resources.restore route bound as POST under the panel path', function (): void {
    $route = app('router')->getRoutes()->getByName('arqel.resources.restore');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->uri())->toContain('{resource}/{id}/restore');
});

it('restores a soft-deleted record and flashes success', function (): void {
    // Soft-delete the record first: the global scope now hides it.
    SoftStub::query()->find(1)->delete();
    expect(SoftStub::query()->find(1))->toBeNull()
        ->and(SoftStub::withTrashed()->find(1)->trashed())->toBeTrue();

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/soft-restore/1/restore', 'POST');
    $response = $controller->restore($request, 'soft-restore', '1');

    $fresh = SoftStub::query()->find(1);

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse()
        ->and($response->getSession()->get('success'))->toBe(__('arqel::messages.flash.restored'))
        ->and($response->getSession()->get('error'))->toBeNull();
});

it('loads the trashed record for restore without 404 (findWithTrashedOrFail bypasses the scope)', function (): void {
    SoftStub::query()->find(1)->delete();

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    // If the restore path used the default (scoped) query the trashed row
    // would be invisible → 404. A successful restore proves it loaded.
    $controller->restore(Request::create('/x', 'POST'), 'soft-restore', '1');

    expect(SoftStub::query()->find(1))->not->toBeNull();
});

it('aborts 404 when restoring a non-existent record id', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $controller->restore(Request::create('/x', 'POST'), 'soft-restore', '999');
})->throws(HttpException::class);
