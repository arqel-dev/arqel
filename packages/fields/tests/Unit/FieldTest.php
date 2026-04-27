<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;
use Illuminate\Database\Eloquent\Model;

it('exposes the subclass type and component', function (): void {
    $field = new StubField('email');

    expect($field->getType())->toBe('stub')
        ->and($field->getComponent())->toBe('StubInput')
        ->and($field->getName())->toBe('email');
});

it('auto-derives the label from the field name', function (): void {
    expect((new StubField('first_name'))->getLabel())->toBe('First Name')
        ->and((new StubField('email'))->getLabel())->toBe('Email')
        ->and((new StubField('billing_address_line_1'))->getLabel())
        ->toBe('Billing Address Line 1');
});

it('honours an explicit label override', function (): void {
    $field = (new StubField('first_name'))->label('Given name');

    expect($field->getLabel())->toBe('Given name');
});

it('chains fluent setters returning the same instance', function (): void {
    $field = new StubField('email');

    $result = $field
        ->label('E-mail')
        ->placeholder('you@example.com')
        ->helperText('We never share it.')
        ->default('hello@arqel.dev')
        ->readonly()
        ->columnSpan(6);

    expect($result)->toBe($field)
        ->and($field->getPlaceholder())->toBe('you@example.com')
        ->and($field->getHelperText())->toBe('We never share it.')
        ->and($field->getDefault())->toBe('hello@arqel.dev')
        ->and($field->isReadonly())->toBeTrue()
        ->and($field->getColumnSpan())->toBe(6);
});

it('supports columnSpanFull as a shortcut', function (): void {
    expect((new StubField('bio'))->columnSpanFull()->getColumnSpan())->toBe('full');
});

it('evaluates a Closure-based disabled callback against the record', function (): void {
    $field = (new StubField('email'))->disabled(
        fn (?Model $record) => $record !== null,
    );

    expect($field->isDisabled(null))->toBeFalse()
        ->and($field->isDisabled(new class extends Model {}))->toBeTrue();
});

it('treats a static disabled flag as the literal value', function (): void {
    expect((new StubField('email'))->disabled()->isDisabled())->toBeTrue()
        ->and((new StubField('email'))->disabled(false)->isDisabled())->toBeFalse();
});

it('can be marked non-dehydrated for computed fields', function (): void {
    $field = (new StubField('full_name'))->dehydrated(false);

    expect($field->isDehydrated())->toBeFalse();
});

it('evaluates a Closure-based dehydrated callback', function (): void {
    $field = (new StubField('email'))->dehydrated(
        fn (?Model $record) => $record === null,
    );

    expect($field->isDehydrated(null))->toBeTrue()
        ->and($field->isDehydrated(new class extends Model {}))->toBeFalse();
});

it('marks the field as live with sensible debounce defaults', function (): void {
    $instant = (new StubField('email'))->live();
    $debounced = (new StubField('email'))->liveDebounced(500);

    expect($instant->isLive())->toBeTrue()
        ->and($instant->getLiveDebounce())->toBe(0)
        ->and($debounced->isLive())->toBeTrue()
        ->and($debounced->getLiveDebounce())->toBe(500);
});

it('makes the field live automatically when an afterStateUpdated callback is set', function (): void {
    $field = (new StubField('email'))->afterStateUpdated(fn () => null);

    expect($field->isLive())->toBeTrue()
        ->and($field->getAfterStateUpdated())->toBeInstanceOf(Closure::class);
});

it('exposes the subclass type-specific props', function (): void {
    expect((new StubField('email'))->getTypeSpecificProps())->toBe(['stub' => true]);
});
