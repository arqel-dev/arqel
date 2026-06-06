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
 * Regression coverage for #67 (A): the bulk dispatcher must forward the
 * table's serialised columns to a bulk action that accepts them via
 * `withColumns()`. Before the fix, `ExportAction` received an empty
 * column list and produced a BOM-only empty CSV. We duck-type both the
 * column object (a `toArray()`-shaped descriptor) and the bulk action
 * (a `withColumns()` + `execute()` recorder) so this file keeps no hard
 * dependency on `arqel-dev/table` or `arqel-dev/export`.
 */

/**
 * Column-shaped object: mirrors `Arqel\Table\Column`, whose `toArray()`
 * emits `{type, name, label, ...}`. The controller must serialise this
 * before handing it to the action.
 */
final class ColumnDescriptorStub
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => 'string', 'name' => $this->name, 'label' => $this->label];
    }
}

/**
 * Callback-less bulk action that records the columns it was handed.
 * Mirrors how ExportAction exposes `withColumns()` + overrides
 * `execute()` without ever declaring a closure callback.
 */
final class ColumnRecordingBulkAction
{
    /** @var array<int, array<string, mixed>> */
    public static array $receivedColumns = [];

    /** @var array<int, array<string, mixed>> */
    private array $columns = [];

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
     * @param array<int, array<string, mixed>> $columns
     */
    public function withColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(mixed $records = null, array $data = []): mixed
    {
        self::$receivedColumns = $this->columns;

        return ['ok' => true];
    }
}

final class ColumnInjectionTableStub
{
    /** @return array<int, object> */
    public function getColumns(): array
    {
        return [
            new ColumnDescriptorStub('id', 'ID'),
            new ColumnDescriptorStub('name', 'Name'),
        ];
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
        return [new ColumnRecordingBulkAction('export')];
    }

    /** @return array<int, object> */
    public function getToolbarActions(): array
    {
        return [];
    }
}

final class ColumnInjectionResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'column-injection';

    public function fields(): array
    {
        return [];
    }

    public function table(): ColumnInjectionTableStub
    {
        return new ColumnInjectionTableStub;
    }
}

beforeEach(function (): void {
    ColumnRecordingBulkAction::$receivedColumns = [];

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(ColumnInjectionResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
});

it('forwards the serialised table columns to a bulk action that accepts them', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/column-injection/bulk/export', 'POST', [
        'record_ids' => [1, 2],
    ]);

    $controller->bulkAction($request, 'column-injection', 'export');

    expect(ColumnRecordingBulkAction::$receivedColumns)->toBe([
        ['type' => 'string', 'name' => 'id', 'label' => 'ID'],
        ['type' => 'string', 'name' => 'name', 'label' => 'Name'],
    ]);
});
