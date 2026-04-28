<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;

it('defaults to visible in every context', function (): void {
    $field = new StubField('name');

    expect($field->isVisibleIn('create'))->toBeTrue()
        ->and($field->isVisibleIn('edit'))->toBeTrue()
        ->and($field->isVisibleIn('detail'))->toBeTrue()
        ->and($field->isVisibleIn('table'))->toBeTrue();
});

it('hides the field everywhere when hidden() is called', function (): void {
    $field = (new StubField('name'))->hidden();

    expect($field->isVisibleIn('create'))->toBeFalse()
        ->and($field->isVisibleIn('detail'))->toBeFalse();
});

it('flips per-context flags individually', function (): void {
    $field = (new StubField('name'))
        ->hiddenOnCreate()
        ->hiddenOnTable();

    expect($field->isVisibleIn('create'))->toBeFalse()
        ->and($field->isVisibleIn('table'))->toBeFalse()
        ->and($field->isVisibleIn('edit'))->toBeTrue()
        ->and($field->isVisibleIn('detail'))->toBeTrue();
});

it('whitelists contexts via visibleOn', function (): void {
    $field = (new StubField('name'))->visibleOn(['edit', 'detail']);

    expect($field->isVisibleIn('edit'))->toBeTrue()
        ->and($field->isVisibleIn('detail'))->toBeTrue()
        ->and($field->isVisibleIn('create'))->toBeFalse()
        ->and($field->isVisibleIn('table'))->toBeFalse();
});

it('hides selected contexts via hiddenOn', function (): void {
    $field = (new StubField('password'))->hiddenOn(['table', 'detail']);

    expect($field->isVisibleIn('table'))->toBeFalse()
        ->and($field->isVisibleIn('detail'))->toBeFalse()
        ->and($field->isVisibleIn('create'))->toBeTrue()
        ->and($field->isVisibleIn('edit'))->toBeTrue();
});

it('rejects unknown context names', function (): void {
    (new StubField('x'))->visibleOn('invalidContext');
})->throws(InvalidArgumentException::class, 'Unknown visibility context');

it('evaluates visibleIf with the record argument', function (): void {
    $field = (new StubField('admin_notes'))
        ->visibleIf(fn ($record) => $record !== null);

    expect($field->isVisibleIn('detail'))->toBeFalse()
        ->and($field->isVisibleIn('detail', mockRecord()))->toBeTrue();
});

it('evaluates hiddenIf with the record argument', function (): void {
    $field = (new StubField('legacy'))
        ->hiddenIf(fn ($record) => $record !== null);

    expect($field->isVisibleIn('edit', mockRecord()))->toBeFalse()
        ->and($field->isVisibleIn('edit'))->toBeTrue();
});

it('rejects combining visibleIf with hiddenIf', function (): void {
    (new StubField('x'))
        ->visibleIf(fn () => true)
        ->hiddenIf(fn () => true);
})->throws(LogicException::class, 'Cannot combine');

it('rejects combining hiddenIf with visibleIf', function (): void {
    (new StubField('x'))
        ->hiddenIf(fn () => true)
        ->visibleIf(fn () => true);
})->throws(LogicException::class, 'Cannot combine');

it('combines hiddenOnCreate with visibleIf — both gates must pass', function (): void {
    $field = (new StubField('audit_trail'))
        ->hiddenOnCreate()
        ->visibleIf(fn ($record) => $record !== null);

    expect($field->isVisibleIn('create', mockRecord()))->toBeFalse()
        ->and($field->isVisibleIn('detail'))->toBeFalse()
        ->and($field->isVisibleIn('detail', mockRecord()))->toBeTrue();
});

function mockRecord(): Illuminate\Database\Eloquent\Model
{
    return new class extends Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    };
}
