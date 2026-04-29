<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Http\Request;

/**
 * FORM-006: when a Resource declares `form(): Form` (with Section/
 * Tabs/Grid layout), the create/edit/show payloads carry an
 * additional `form` key (= `Form::toArray()`) and the `fields` key
 * is sourced from `Form::getFields()` (flatten) instead of
 * `Resource::fields()`. Resources that don't declare a form fall
 * back to the existing flat field list — no `form` key emitted.
 *
 * `arqel/core` is duck-typed against `arqel/form`, so we drive the
 * test with a fake Form class implementing the contract
 * (`getFields`/`getSchema`/`toArray`) instead of pulling
 * `arqel/form` as a dev dep.
 */
final class FakeField
{
    public function __construct(public string $name) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'text';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => 'text',
            'label' => ucfirst($this->name),
        ];
    }
}

final class FakeForm
{
    /** @param  list<FakeField>  $fields */
    public function __construct(public array $fields = []) {}

    /** @return list<FakeField> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /** @return list<array<string, mixed>> */
    public function getSchema(): array
    {
        return [
            ['kind' => 'layout', 'type' => 'section', 'heading' => 'Details'],
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'columns' => 2,
            'schema' => $this->getSchema(),
        ];
    }
}

final class FormDrivenResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'form-driven';

    /** @var list<FakeField> */
    public static array $formFields = [];

    public function fields(): array
    {
        // Intentionally returns a different list to prove the
        // builder picks Form::getFields() over Resource::fields()
        // when a form is declared.
        return [new FakeField('legacy_field')];
    }

    public function form(): mixed
    {
        if (self::$formFields === []) {
            return null;
        }

        return new FakeForm(self::$formFields);
    }
}

beforeEach(function (): void {
    FormDrivenResource::$formFields = [];
});

it('emits no `form` key and uses Resource::fields() when no form is declared', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new FormDrivenResource, new Request);

    expect($payload)->toHaveKey('fields')
        ->and($payload)->not->toHaveKey('form')
        ->and($payload['fields'])->toHaveCount(1)
        ->and($payload['fields'][0]['name'])->toBe('legacy_field');
});

it('emits the `form` schema and pulls fields from Form::getFields() when declared', function (): void {
    FormDrivenResource::$formFields = [
        new FakeField('title'),
        new FakeField('body'),
    ];

    $payload = app(InertiaDataBuilder::class)->buildCreateData(new FormDrivenResource, new Request);

    expect($payload['form'])
        ->toBe([
            'columns' => 2,
            'schema' => [
                ['kind' => 'layout', 'type' => 'section', 'heading' => 'Details'],
            ],
        ])
        ->and($payload['fields'])->toHaveCount(2)
        ->and($payload['fields'][0]['name'])->toBe('title')
        ->and($payload['fields'][1]['name'])->toBe('body');
});

it('propagates the form payload through buildEditData with a record', function (): void {
    FormDrivenResource::$formFields = [new FakeField('title')];

    $record = new Stub(['name' => 'sample']);
    $record->setRawAttributes(['id' => 1, 'name' => 'sample']);

    $payload = app(InertiaDataBuilder::class)->buildEditData(new FormDrivenResource, $record, new Request);

    expect($payload)->toHaveKey('form')
        ->and($payload)->toHaveKey('record')
        ->and($payload)->toHaveKey('recordTitle')
        ->and($payload)->toHaveKey('recordSubtitle')
        ->and($payload['form']['columns'])->toBe(2);
});

it('propagates the form payload through buildShowData', function (): void {
    FormDrivenResource::$formFields = [new FakeField('title')];

    $record = new Stub(['name' => 'sample']);
    $record->setRawAttributes(['id' => 1, 'name' => 'sample']);

    $payload = app(InertiaDataBuilder::class)->buildShowData(new FormDrivenResource, $record, new Request);

    expect($payload['form']['columns'])->toBe(2);
});

it('falls back to Resource::fields() when form() returns a non-Form value', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = Stub::class;

        public static ?string $slug = 'broken-form';

        public function fields(): array
        {
            return [new FakeField('fallback')];
        }

        public function form(): mixed
        {
            return 'not-a-form';
        }
    };

    $payload = app(InertiaDataBuilder::class)->buildCreateData($resource, new Request);

    expect($payload)->not->toHaveKey('form')
        ->and($payload['fields'][0]['name'])->toBe('fallback');
});
