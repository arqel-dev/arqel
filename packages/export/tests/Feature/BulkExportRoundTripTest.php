<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end regression coverage for #67. Exercises the real
 * controller -> ExportAction -> CsvExporter seam: a bulk export over a
 * Resource whose Table carries real columns + selected records must
 * produce a populated CSV (headers + a row per record), write it to the
 * dir the download controller reads, and flash a retrievable download
 * URL pointing at the `arqel.export.download` route.
 *
 * `arqel-dev/table` is not a dependency of the export package, so the
 * Table + Column objects here are duck-typed to the exact shape the
 * production code consumes (Column::toArray() => {type, name, label};
 * Table::getColumns()/getBulkActions()).
 */

/** Column-shaped descriptor mirroring Arqel\Table\Column::toArray(). */
final class RoundTripColumn
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

/** Table-shaped object carrying real columns + a real ExportAction. */
final class RoundTripTable
{
    public function __construct(private readonly ExportAction $exportAction) {}

    /** @return array<int, object> */
    public function getColumns(): array
    {
        return [
            new RoundTripColumn('id', 'ID'),
            new RoundTripColumn('name', 'Name'),
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
        return [$this->exportAction];
    }

    /** @return array<int, object> */
    public function getToolbarActions(): array
    {
        return [];
    }
}

final class RoundTripExportModel extends Illuminate\Database\Eloquent\Model
{
    protected $table = 'round_trip_records';

    public $timestamps = false;

    protected $guarded = [];
}

final class RoundTripExportResource extends Resource
{
    public static string $model = RoundTripExportModel::class;

    public static ?string $slug = 'round-trip';

    public static ?ExportAction $exportAction = null;

    public function fields(): array
    {
        return [];
    }

    public function table(): mixed
    {
        return new RoundTripTable(self::$exportAction ?? ExportAction::make('export'));
    }
}

beforeEach(function (): void {
    $this->exportDir = sys_get_temp_dir().'/arqel-roundtrip-'.bin2hex(random_bytes(4));
    mkdir($this->exportDir, 0o755, true);

    // Point the download controller at the same dir the action writes to.
    config()->set('arqel-export.destination_dir', $this->exportDir);

    RoundTripExportResource::$exportAction = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withDestinationDir($this->exportDir);

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RoundTripExportResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('round_trip_records', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
    });

    RoundTripExportModel::query()->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Carol'],
    ]);
});

afterEach(function (): void {
    if (isset($this->exportDir) && is_string($this->exportDir) && is_dir($this->exportDir)) {
        foreach ((array) glob($this->exportDir.'/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->exportDir);
    }
    RoundTripExportResource::$exportAction = null;
});

it('produces a populated CSV with table headers and a row per selected record (A)', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/round-trip/bulk/export', 'POST', [
        'record_ids' => [1, 2],
    ]);

    $controller->bulkAction($request, 'round-trip', 'export');

    $files = (array) glob($this->exportDir.'/export-*.csv');
    expect($files)->toHaveCount(1);

    $contents = (string) file_get_contents((string) $files[0]);
    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    }

    // NOT a BOM-only empty file: real headers + a data row per record.
    $lines = array_values(array_filter(explode("\n", trim($contents)), fn (string $l): bool => $l !== ''));

    expect($lines[0])->toContain('ID')
        ->and($lines[0])->toContain('Name')
        ->and($lines)->toHaveCount(3) // header + 2 selected rows
        ->and($contents)->toContain('Alice')
        ->and($contents)->toContain('Bob')
        ->and($contents)->not->toContain('Carol');
});

it('writes to the download dir and flashes a retrievable download URL (B)', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/round-trip/bulk/export', 'POST', [
        'record_ids' => [1, 2],
    ]);

    $response = $controller->bulkAction($request, 'round-trip', 'export');

    $session = $response->getSession();
    $url = $session->get('download_url');

    expect($url)->toBeString()
        ->and($url)->toContain('/admin/exports/')
        ->and($url)->toContain('/download');

    // The flashed URL's export id must resolve to the produced file via
    // the download controller's glob (filename scheme alignment).
    expect($session->get('error'))->toBeNull()
        ->and($session->get('success'))->not->toBeNull();

    $files = (array) glob($this->exportDir.'/export-*.csv');
    expect($files)->toHaveCount(1);

    // Extract the export id from the produced filename and confirm it is
    // embedded in the flashed URL.
    $basename = basename((string) $files[0], '.csv');
    $exportId = substr($basename, strlen('export-'));
    expect($url)->toContain($exportId);
});
