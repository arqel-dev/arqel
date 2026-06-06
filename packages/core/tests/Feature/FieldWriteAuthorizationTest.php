<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Field-level write authorization (#102).
 *
 * `canSee()`/`canEdit()` predicates must be enforced on the write
 * path, not only when serialising the render payload. A user shown a
 * read-only or hidden field could otherwise submit its value and have
 * it persisted (mass-assignment bypass).
 *
 * Core stays decoupled from `arqel-dev/fields`, so these fixtures
 * duck-type the exact contract the controller consumes: `getName()`
 * plus the `canBeSeenBy()` / `canBeEditedBy()` oracle pair from the
 * `HasAuthorization` trait.
 */
final class WriteAuthField
{
    /** @var (callable(?Authenticatable, ?Model): bool)|null */
    private $canEdit;

    /** @var (callable(?Authenticatable, ?Model): bool)|null */
    private $canSee;

    public function __construct(
        private readonly string $name,
        ?callable $canEdit = null,
        ?callable $canSee = null,
    ) {
        $this->canEdit = $canEdit;
        $this->canSee = $canSee;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function canBeSeenBy(?Authenticatable $user = null, ?Model $record = null): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($user, $record);
    }

    public function canBeEditedBy(?Authenticatable $user = null, ?Model $record = null): bool
    {
        if (! $this->canBeSeenBy($user, $record)) {
            return false;
        }

        if ($this->canEdit === null) {
            return true;
        }

        return (bool) ($this->canEdit)($user, $record);
    }
}

final class WriteAuthResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'write-auth';

    public function fields(): array
    {
        return [
            new WriteAuthField('name'),
            new WriteAuthField('featured', canEdit: fn () => false),
            new WriteAuthField('secret', canSee: fn () => false),
        ];
    }
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(WriteAuthResource::class);

    $this->builder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->boolean('featured')->default(false);
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    Route::get('/{resource}/{id}/edit', fn () => 'ok')->name('arqel.resources.edit');
    Route::get('/{resource}', fn () => 'ok')->name('arqel.resources.index');
});

it('store: a canEdit(false) field is pruned from the request', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/write-auth', 'POST', [
        'name' => 'Alice',
        'featured' => 1,
    ]);

    $controller->store($request, 'write-auth');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Alice')
        ->and((bool) $record->featured)->toBeFalse();
});

it('store: a hidden (canSee false) field is pruned from the request', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/write-auth', 'POST', [
        'name' => 'Alice',
        'secret' => 'leaked',
    ]);

    $controller->store($request, 'write-auth');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Alice')
        ->and($record->secret)->toBeNull();
});

it('update: a canEdit(false) field keeps its existing value', function (): void {
    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice', 'featured' => true, 'secret' => 'keep'],
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/write-auth/1', 'PUT', [
        'name' => 'Alice Updated',
        'featured' => 0,
        'secret' => 'overwrite',
    ]);

    $controller->update($request, 'write-auth', '1');

    $record = Stub::query()->findOrFail(1);

    expect($record->name)->toBe('Alice Updated')
        ->and((bool) $record->featured)->toBeTrue()
        ->and($record->secret)->toBe('keep');
});

it('store: fields with no predicate (the default) stay writable', function (): void {
    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/write-auth', 'POST', [
        'name' => 'Writable',
    ]);

    $controller->store($request, 'write-auth');

    $record = Stub::query()->firstOrFail();

    expect($record->name)->toBe('Writable');
});
