<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Resource subclass with a couple of "Field-like" objects to
 * exercise InertiaDataBuilder::serializeFields without requiring
 * arqel/fields as a hard dependency.
 */
final class FakeFieldedResource extends Resource
{
    public static string $model = Stub::class;

    public function fields(): array
    {
        return [
            new class
            {
                public function toArray(): array
                {
                    return ['name' => 'email', 'type' => 'text', 'extra' => true];
                }
            },
            new class
            {
                public function getName(): string
                {
                    return 'created_at';
                }

                public function getType(): string
                {
                    return 'datetime';
                }
            },
            'not-a-field-instance',
        ];
    }
}

beforeEach(function (): void {
    $this->builder = app(InertiaDataBuilder::class);
});

it('buildCreateData returns resource meta and serialised fields with no record', function (): void {
    $data = $this->builder->buildCreateData(new UserResource, new Request);

    expect($data)->toHaveKeys(['resource', 'record', 'fields'])
        ->and($data['record'])->toBeNull()
        ->and($data['resource']['slug'])->toBe('users')
        ->and($data['resource']['navigationGroup'])->toBe('System')
        ->and($data['fields'])->toBe([]);
});

it('buildEditData includes the record array, recordTitle, and fields', function (): void {
    $resource = new UserResource;

    /** @var Stub $record */
    $record = new Stub;
    $record->forceFill(['id' => 7, 'name' => 'Alice']);

    $data = $this->builder->buildEditData($resource, $record, new Request);

    expect($data['record'])->toMatchArray(['id' => 7, 'name' => 'Alice'])
        ->and($data['recordTitle'])->toBe('7')
        ->and($data['recordSubtitle'])->toBeNull();
});

it('buildShowData mirrors buildEditData', function (): void {
    $resource = new UserResource;

    /** @var Stub $record */
    $record = new Stub;
    $record->forceFill(['id' => 1]);

    $data = $this->builder->buildShowData($resource, $record, new Request);

    expect($data)->toHaveKeys(['resource', 'record', 'recordTitle', 'recordSubtitle', 'fields']);
});

it('serializeFields prefers ->toArray() and falls back to {name,type}', function (): void {
    $data = $this->builder->buildCreateData(new FakeFieldedResource, new Request);

    expect($data['fields'])->toBe([
        ['name' => 'email', 'type' => 'text', 'extra' => true],
        ['name' => 'created_at', 'type' => 'datetime'],
    ]);
});
