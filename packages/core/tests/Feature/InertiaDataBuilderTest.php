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
 * arqel-dev/fields as a hard dependency.
 */
final class FakeFieldedResource extends Resource
{
    public static string $model = Stub::class;

    public function fields(): array
    {
        return [
            new class
            {
                public function getName(): string
                {
                    return 'email';
                }

                public function getType(): string
                {
                    return 'text';
                }

                public function getTypeSpecificProps(): array
                {
                    return ['extra' => true];
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

final class SchemaArrayFieldsResource extends Resource
{
    public static string $model = Stub::class;

    public function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'text', 'label' => 'E-mail'],
            ['name' => 'hidden_in_table', 'visibility' => ['table' => false]],
            ['name' => '', 'type' => 'text'],            // skipped (empty name)
            ['type' => 'text'],                           // skipped (no name)
            'not-an-array',                               // skipped (string)
        ];
    }
}

it('buildIndexData derives columns from array-style fields when no Table is declared', function (): void {
    Illuminate\Support\Facades\Schema::create('stubs', function ($table) {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice', 'email' => 'a@x.test'],
        ['id' => 2, 'name' => 'Bob', 'email' => 'b@x.test'],
    ]);

    $data = $this->builder->buildIndexData(new SchemaArrayFieldsResource, new Request);

    expect($data['columns'])->toHaveCount(2)
        ->and($data['columns'][0])->toMatchArray([
            'name' => 'name',
            'type' => 'text',
            'label' => 'Name',
            'sortable' => false,
            'searchable' => false,
            'copyable' => false,
            'hidden' => false,
            'hiddenOnMobile' => false,
            'align' => 'start',
            'width' => null,
            'tooltip' => null,
        ])
        ->and($data['columns'][0]['props'])->toBeObject()
        ->and($data['columns'][1])->toMatchArray([
            'name' => 'email',
            'label' => 'E-mail',
        ]);
});

it('delegates field serialisation to FieldSchemaSerializer', function (): void {
    $data = $this->builder->buildCreateData(new FakeFieldedResource, new Request);

    // The serializer emits the canonical rich shape — we just
    // verify the contract (name+type+props) for the duck-typed
    // fields. Snapshot coverage of the full shape lives in
    // FieldSchemaSerializerTest.
    expect($data['fields'])->toHaveCount(2)
        ->and($data['fields'][0])->toHaveKeys(['type', 'name', 'validation', 'visibility', 'props'])
        ->and($data['fields'][0]['name'])->toBe('email')
        ->and($data['fields'][0]['type'])->toBe('text')
        ->and($data['fields'][1]['name'])->toBe('created_at')
        ->and($data['fields'][1]['type'])->toBe('datetime');
});

it('resourceMeta includes panelPath defaulting to /admin when no panel is registered', function (): void {
    $resource = new UserResource;
    $data = $this->builder->buildCreateData($resource, new Request);

    expect($data['resource']['panelPath'])->toBe('/admin');
});

it('resourceMeta panelPath honours custom arqel.path config', function (): void {
    config()->set('arqel.path', 'dashboard');

    $resource = new UserResource;
    $data = $this->builder->buildCreateData($resource, new Request);

    expect($data['resource']['panelPath'])->toBe('/dashboard');
});
