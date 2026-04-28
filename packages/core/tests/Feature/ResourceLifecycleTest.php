<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Database\Eloquent\Model;

/**
 * Tests for the public lifecycle orchestrators on Resource:
 * `runCreate` / `runUpdate` / `runDelete`. Hooks are inspected
 * via a hand-rolled Resource subclass that records each call.
 */
final class RecordingResource extends Resource
{
    public static string $model = Stub::class;

    /** @var array<int, string> */
    public array $callLog = [];

    public function fields(): array
    {
        return [];
    }

    protected function beforeSave(Model $record, array $data): array
    {
        $this->callLog[] = 'beforeSave';
        $data['saved_via_hook'] = true;

        return $data;
    }

    protected function beforeCreate(array $data): array
    {
        $this->callLog[] = 'beforeCreate';

        return $data;
    }

    protected function beforeUpdate(Model $record, array $data): array
    {
        $this->callLog[] = 'beforeUpdate';

        return $data;
    }

    protected function afterCreate(Model $record): void
    {
        $this->callLog[] = 'afterCreate';
    }

    protected function afterUpdate(Model $record): void
    {
        $this->callLog[] = 'afterUpdate';
    }

    protected function afterSave(Model $record): void
    {
        $this->callLog[] = 'afterSave';
    }

    protected function beforeDelete(Model $record): void
    {
        $this->callLog[] = 'beforeDelete';
    }

    protected function afterDelete(Model $record): void
    {
        $this->callLog[] = 'afterDelete';
    }
}

it('runCreate fires beforeSave → beforeCreate → afterCreate → afterSave in order', function (): void {
    $resource = new RecordingResource;

    $record = Mockery::mock(Stub::class)->makePartial();
    $record->shouldReceive('fill')->once()->withArgs(function (array $data): bool {
        expect($data)->toHaveKey('saved_via_hook')
            ->and($data['saved_via_hook'])->toBeTrue();

        return true;
    })->andReturnSelf();
    $record->shouldReceive('save')->once()->andReturnTrue();

    // Replace `new $modelClass` by overriding the model class to point at
    // the partial mock through the container.
    app()->bind(Stub::class, fn () => $record);

    $resource->runCreate(['name' => 'Alice']);

    expect($resource->callLog)->toBe([
        'beforeSave',
        'beforeCreate',
        'afterCreate',
        'afterSave',
    ]);
});

it('runUpdate fires beforeSave → beforeUpdate → afterUpdate → afterSave', function (): void {
    $resource = new RecordingResource;

    $record = Mockery::mock(Stub::class)->makePartial();
    $record->shouldReceive('fill')->once()->andReturnSelf();
    $record->shouldReceive('save')->once()->andReturnTrue();

    $resource->runUpdate($record, ['name' => 'Bob']);

    expect($resource->callLog)->toBe([
        'beforeSave',
        'beforeUpdate',
        'afterUpdate',
        'afterSave',
    ]);
});

it('runDelete fires beforeDelete then afterDelete only when delete returns truthy', function (): void {
    $resource = new RecordingResource;

    $record = Mockery::mock(Stub::class)->makePartial();
    $record->shouldReceive('delete')->once()->andReturnTrue();

    $result = $resource->runDelete($record);

    expect($result)->toBeTrue()
        ->and($resource->callLog)->toBe(['beforeDelete', 'afterDelete']);
});

it('runDelete skips afterDelete when delete returns falsey', function (): void {
    $resource = new RecordingResource;

    $record = Mockery::mock(Stub::class)->makePartial();
    $record->shouldReceive('delete')->once()->andReturnFalse();

    $result = $resource->runDelete($record);

    expect($result)->toBeFalse()
        ->and($resource->callLog)->toBe(['beforeDelete']);
});
