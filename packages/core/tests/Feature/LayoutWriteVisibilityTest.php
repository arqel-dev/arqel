<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Layout-level write visibility (#115).
 *
 * A field whose only guard is an enclosing hidden layout
 * (`Section::canSee(...)`) must not be persisted: the controller
 * threads the current record into `effectiveFields($record)`, which a
 * layout-aware form() prunes, and `pruneUnauthorizedFields` drops the
 * submitted value for any field excluded by that pruning — mirroring
 * the render payload, which also omits it.
 *
 * Core stays decoupled from `arqel-dev/form`, so this fixture
 * duck-types the contract the controller consumes: a form whose
 * `getFields(?Model $record)` prunes a "secret" field when the record
 * is locked, exactly as a real `Section::canSee()` would.
 */
final class LayoutVisField
{
    public function __construct(private readonly string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'text';
    }
}

final class LayoutVisForm
{
    /** @return list<LayoutVisField> */
    public function getFields(?Model $record = null): array
    {
        $fields = [new LayoutVisField('name')];

        // The "secret" field lives inside a Section the record cannot
        // see once it is locked — so it drops out of the flat list.
        $locked = $record !== null && (bool) $record->getAttribute('locked');

        if (! $locked) {
            $fields[] = new LayoutVisField('secret');
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    public function toArray(?Model $record = null): array
    {
        return ['schema' => [], 'columns' => 1];
    }
}

final class LayoutVisResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'layout-vis';

    public function fields(): array
    {
        return [new LayoutVisField('name'), new LayoutVisField('secret')];
    }

    public function form(): mixed
    {
        return new LayoutVisForm;
    }
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(LayoutVisResource::class);

    $this->builder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('secret')->nullable();
        $table->boolean('locked')->default(false);
        $table->timestamps();
    });

    Route::get('/{resource}/{id}/edit', fn () => 'ok')->name('arqel.resources.edit');
    Route::get('/{resource}', fn () => 'ok')->name('arqel.resources.index');
});

it('update: a field under a record-hidden layout is not persisted', function (): void {
    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice', 'secret' => 'keep', 'locked' => true],
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/layout-vis/1', 'PUT', [
        'name' => 'Alice Updated',
        'secret' => 'overwrite',
    ]);

    $controller->update($request, 'layout-vis', '1');

    $record = Stub::query()->findOrFail(1);

    expect($record->name)->toBe('Alice Updated')
        ->and($record->secret)->toBe('keep');
});

it('update: a field under a visible layout is still persisted', function (): void {
    Stub::query()->insert([
        ['id' => 1, 'name' => 'Bob', 'secret' => 'old', 'locked' => false],
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/layout-vis/1', 'PUT', [
        'name' => 'Bob Updated',
        'secret' => 'new',
    ]);

    $controller->update($request, 'layout-vis', '1');

    $record = Stub::query()->findOrFail(1);

    expect($record->name)->toBe('Bob Updated')
        ->and($record->secret)->toBe('new');
});
