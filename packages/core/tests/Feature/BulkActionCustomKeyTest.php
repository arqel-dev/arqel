<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\CustomKeyStub;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Regression coverage for #69: `bulkAction` resolved records with a
 * hardcoded `whereIn('id', ...)` for both the get and the stock delete.
 * Any model with a non-default primary key (`$primaryKey = 'uuid'`)
 * therefore matched zero records, so bulk delete/export silently
 * touched nothing. The dispatcher must derive the key from the model's
 * real `getKeyName()`, mirroring `ActionController::invokeBulk`.
 *
 * We duck-type the Table contract exactly like the sibling bulk-action
 * tests so this file keeps no hard dep on `arqel-dev/table`.
 */
final class CustomKeyDeleteTableStub
{
    /** @return array<int, object> */
    public function getColumns(): array
    {
        return [];
    }

    /** @return array<int, object> */
    public function getFilters(): array
    {
        return [];
    }

    /** @return array<int, object> */
    public function getActions(): array
    {
        return [];
    }

    /** @return array<int, object> */
    public function getBulkActions(): array
    {
        return [new StockDeleteBulkActionStub('delete')];
    }

    /** @return array<int, object> */
    public function getToolbarActions(): array
    {
        return [];
    }
}

/**
 * Stock-delete bulk action stub: carries the `delete` name and reports
 * no callback, so the controller takes its fast `whereIn(...)->delete()`
 * path (the very site that hardcoded `id`).
 */
final class StockDeleteBulkActionStub
{
    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function hasCallback(): bool
    {
        return false;
    }
}

final class CustomKeyResource extends Resource
{
    public static string $model = CustomKeyStub::class;

    public static ?string $slug = 'custom-key';

    public function fields(): array
    {
        return [];
    }

    public function table(): CustomKeyDeleteTableStub
    {
        return new CustomKeyDeleteTableStub;
    }
}

final class DefaultKeyResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'default-key';

    public function fields(): array
    {
        return [];
    }

    public function table(): CustomKeyDeleteTableStub
    {
        return new CustomKeyDeleteTableStub;
    }
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(CustomKeyResource::class);
    $this->registry->register(DefaultKeyResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('custom_key_stubs', function ($table): void {
        $table->string('uuid')->primary();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->timestamps();
    });

    CustomKeyStub::query()->insert([
        ['uuid' => 'aaaaaaaa-0000-0000-0000-000000000001', 'name' => 'Alice'],
        ['uuid' => 'bbbbbbbb-0000-0000-0000-000000000002', 'name' => 'Bob'],
        ['uuid' => 'cccccccc-0000-0000-0000-000000000003', 'name' => 'Carol'],
    ]);

    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Carol'],
    ]);
});

it('bulk-deletes records of a model with a non-default primary key', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/custom-key/bulk/delete', 'POST', [
        'record_ids' => [
            'aaaaaaaa-0000-0000-0000-000000000001',
            'bbbbbbbb-0000-0000-0000-000000000002',
        ],
    ]);

    $controller->bulkAction($request, 'custom-key', 'delete');

    expect(CustomKeyStub::query()->count())->toBe(1)
        ->and(CustomKeyStub::query()->find('cccccccc-0000-0000-0000-000000000003'))->not->toBeNull();
});

it('still bulk-deletes records of a model with the default id key (no regression)', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/default-key/bulk/delete', 'POST', [
        'record_ids' => [1, 2],
    ]);

    $controller->bulkAction($request, 'default-key', 'delete');

    expect(Stub::query()->count())->toBe(1)
        ->and(Stub::query()->find(3))->not->toBeNull();
});
