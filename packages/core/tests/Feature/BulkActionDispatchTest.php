<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Regression coverage for #48 (A): the bulk dispatcher must run any
 * found bulk action through `execute()`, not only ones that carry a
 * closure callback. Actions like `ExportAction` override `execute()`
 * directly and never set `$this->action`, so `hasCallback()` is
 * `false` — the old gate no-opped them with an error flash.
 *
 * We duck-type the Action + Table contracts (no hard dep on
 * `arqel-dev/actions`) exactly like InertiaTableIntegrationTest, so a
 * tiny callback-less bulk action whose `execute()` records a side
 * effect proves the dispatch path fires.
 */

/**
 * Callback-less bulk action: mirrors how ExportAction overrides
 * `execute()` without ever calling `->action()`, so `hasCallback()`
 * is false. The side effect is captured in a public static counter.
 */
final class SideEffectBulkAction
{
    /** @var array<int, mixed> */
    public static array $executedWith = [];

    public static int $executions = 0;

    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function hasCallback(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(mixed $records = null, array $data = []): mixed
    {
        self::$executions++;
        self::$executedWith = is_iterable($records) ? collect($records)->all() : [];

        return ['ok' => true];
    }
}

final class BulkActionTableStub
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
        return [new SideEffectBulkAction('export')];
    }

    /** @return array<int, object> */
    public function getToolbarActions(): array
    {
        return [];
    }
}

final class BulkDispatchResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'bulk-dispatch';

    public function fields(): array
    {
        return [];
    }

    public function table(): BulkActionTableStub
    {
        return new BulkActionTableStub;
    }
}

beforeEach(function (): void {
    SideEffectBulkAction::$executions = 0;
    SideEffectBulkAction::$executedWith = [];

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(BulkDispatchResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Carol'],
    ]);
});

it('runs a callback-less bulk action through execute() instead of flashing an error', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/bulk-dispatch/bulk/export', 'POST', [
        'record_ids' => [1, 2],
    ]);

    $response = $controller->bulkAction($request, 'bulk-dispatch', 'export');

    expect(SideEffectBulkAction::$executions)->toBe(1)
        ->and(SideEffectBulkAction::$executedWith)->toHaveCount(2)
        ->and($response->getSession()->get('error'))->toBeNull()
        ->and($response->getSession()->get('success'))->not->toBeNull();
});
