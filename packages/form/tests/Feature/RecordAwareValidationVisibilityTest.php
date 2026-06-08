<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Record-aware validation vs. layout visibility (#198) — completes the
 * validation leg of #115.
 *
 * `ResourceController::extractRules()` must source rules from
 * `effectiveFields($record)` (the same record-aware list the render and
 * write-prune paths use), NOT the no-arg `effectiveFields()`. Otherwise a
 * `required` field wrapped in a `Section::visibleIf(fn ($r) => ...)` that is
 * HIDDEN for the loaded record is still validated as required: the field is
 * never rendered (the user cannot supply it) yet the request 422s — an
 * unsubmittable update — and even if supplied, the write-prune drops it.
 *
 * Exercised through the real ResourceController with core + form + fields
 * booted (real FieldRulesExtractor + real Section/visibleIf).
 */
final class RecordVisModel extends Model
{
    protected $table = 'record_vis_records';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['locked' => 'boolean'];

    public $timestamps = false;
}

final class RecordVisResource extends Resource
{
    public static string $model = RecordVisModel::class;

    public static ?string $slug = 'record-vis';

    public function fields(): array
    {
        return [];
    }

    public function form(): Form
    {
        return Form::make()->schema([
            (new TextField('name'))->required(),
            Section::make('Secret')
                // Hidden once the record is locked; visible for an unlocked
                // record AND for null (create), matching the render path.
                ->visibleIf(fn (?Model $record): bool => $record?->getAttribute('locked') !== true)
                ->schema([
                    (new TextField('secret_code'))->required(),
                ]),
        ]);
    }
}

beforeEach(function (): void {
    Schema::create('record_vis_records', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('secret_code')->nullable();
        $table->boolean('locked')->default(false);
    });

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RecordVisResource::class);

    $this->builder = app(InertiaDataBuilder::class);

    Route::get('/{resource}/{id}/edit', fn () => 'ok')->name('arqel.resources.edit');
    Route::get('/{resource}', fn () => 'ok')->name('arqel.resources.index');
});

afterEach(function (): void {
    Schema::dropIfExists('record_vis_records');
});

it('update: a required field hidden by a layout for THIS record is not validated (#198)', function (): void {
    $record = RecordVisModel::query()->create([
        'name' => 'Alice',
        'secret_code' => 'keep',
        'locked' => true, // section hidden -> secret_code not rendered
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    // Submit WITHOUT secret_code (the user was never shown it).
    $request = Request::create('/record-vis/'.$record->getKey(), 'PUT', [
        'name' => 'Alice Updated',
    ]);

    $response = $controller->update($request, 'record-vis', (string) $record->getKey());

    // No 422: the hidden field's `required` rule was pruned for this record.
    expect($response->getStatusCode())->toBe(302);

    $fresh = RecordVisModel::query()->findOrFail($record->getKey());
    expect($fresh->name)->toBe('Alice Updated')
        ->and($fresh->secret_code)->toBe('keep');
});

it('update: a required field VISIBLE for THIS record is still validated (no regression)', function (): void {
    $record = RecordVisModel::query()->create([
        'name' => 'Bob',
        'secret_code' => 'old',
        'locked' => false, // section visible -> secret_code IS required
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    // Submit WITHOUT secret_code -> still 422 (the rule IS enforced).
    $request = Request::create('/record-vis/'.$record->getKey(), 'PUT', [
        'name' => 'Bob Updated',
    ]);

    expect(fn () => $controller->update($request, 'record-vis', (string) $record->getKey()))
        ->toThrow(ValidationException::class);
});

it('create: a record-dependent layout is visible for null, so its field is required (no regression)', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    // Create uses a null record -> section visible -> secret_code required.
    $request = Request::create('/record-vis', 'POST', [
        'resource' => 'record-vis',
        'name' => 'Carol',
    ]);

    expect(fn () => $controller->store($request, 'record-vis'))
        ->toThrow(ValidationException::class);
});
