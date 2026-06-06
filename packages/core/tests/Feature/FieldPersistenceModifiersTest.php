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
 * Persistence modifiers on the write path (#127).
 *
 * Distinct from #102/#115 (canEdit/canSee + layout visibility): a
 * field marked display-only or computed via `dehydrated(false)`,
 * `disabled()` or `readonly()` must NOT have a submitted value
 * persisted. `dehydrated(false)` is the documented "don't persist"
 * contract (PLANNING/05-api-php.md:285, PLANNING/08-fase-1-mvp.md:1548);
 * `disabled()`/`readonly()` are serialised display-only, so the
 * developer expects the server to reject any value submitted for them
 * (mass-assignment of display-only/computed fields otherwise).
 *
 * Core stays decoupled from `arqel-dev/fields`, so this fixture
 * duck-types the exact contract the controller consumes: `getName()`
 * plus the persistence oracles `isReadonly()` / `isDisabled(?Model)` /
 * `isDehydrated(?Model)` — matching the real Field signatures.
 */
final class PersistenceModifierField
{
    /** @var bool|(callable(?Model): bool) */
    private $disabled;

    /** @var bool|(callable(?Model): bool) */
    private $dehydrated;

    public function __construct(
        private readonly string $name,
        private readonly bool $readonly = false,
        bool|callable $disabled = false,
        bool|callable $dehydrated = true,
    ) {
        $this->disabled = $disabled;
        $this->dehydrated = $dehydrated;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function isDisabled(?Model $record = null): bool
    {
        if (is_callable($this->disabled)) {
            return (bool) ($this->disabled)($record);
        }

        return $this->disabled;
    }

    public function isDehydrated(?Model $record = null): bool
    {
        if (is_callable($this->dehydrated)) {
            return (bool) ($this->dehydrated)($record);
        }

        return $this->dehydrated;
    }
}

final class PersistenceModifierResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'persistence-modifiers';

    public function fields(): array
    {
        return [
            new PersistenceModifierField('name'),
            new PersistenceModifierField('featured', dehydrated: false),
            new PersistenceModifierField('secret', disabled: true),
            new PersistenceModifierField('locked', readonly: true),
        ];
    }
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(PersistenceModifierResource::class);

    $this->builder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->boolean('featured')->default(false);
        $table->string('secret')->nullable();
        $table->string('locked')->nullable();
        $table->timestamps();
    });

    Route::get('/{resource}/{id}/edit', fn () => 'ok')->name('arqel.resources.edit');
    Route::get('/{resource}', fn () => 'ok')->name('arqel.resources.index');
});

it('store: a dehydrated(false) field value is never persisted', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/persistence-modifiers', 'POST', [
        'name' => 'Alice',
        'featured' => 1,
    ]);

    $controller->store($request, 'persistence-modifiers');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Alice')
        ->and((bool) $record->featured)->toBeFalse();
});

it('store: a disabled() field value is never persisted', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/persistence-modifiers', 'POST', [
        'name' => 'Alice',
        'secret' => 'leaked',
    ]);

    $controller->store($request, 'persistence-modifiers');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Alice')
        ->and($record->secret)->toBeNull();
});

it('store: a readonly() field value is never persisted', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/persistence-modifiers', 'POST', [
        'name' => 'Alice',
        'locked' => 'tampered',
    ]);

    $controller->store($request, 'persistence-modifiers');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Alice')
        ->and($record->locked)->toBeNull();
});

it('update: dehydrated(false)/disabled()/readonly() fields keep their existing value', function (): void {
    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice', 'featured' => true, 'secret' => 'keep', 'locked' => 'keep'],
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/persistence-modifiers/1', 'PUT', [
        'name' => 'Alice Updated',
        'featured' => 0,
        'secret' => 'overwrite',
        'locked' => 'overwrite',
    ]);

    $controller->update($request, 'persistence-modifiers', '1');

    $record = Stub::query()->findOrFail(1);

    expect($record->name)->toBe('Alice Updated')
        ->and((bool) $record->featured)->toBeTrue()
        ->and($record->secret)->toBe('keep')
        ->and($record->locked)->toBe('keep');
});

it('store: a plain field with no persistence modifier still persists', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/persistence-modifiers', 'POST', [
        'name' => 'Writable',
    ]);

    $controller->store($request, 'persistence-modifiers');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Writable');
});
