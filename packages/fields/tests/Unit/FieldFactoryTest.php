<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\Fields\Tests\Fixtures\StubField;

beforeEach(function (): void {
    FieldFactory::flush();
});

afterEach(function (): void {
    FieldFactory::flush();
});

it('registers a Field type and instantiates it via __callStatic', function (): void {
    FieldFactory::register('stub', StubField::class);

    expect(FieldFactory::hasType('stub'))->toBeTrue();

    $field = FieldFactory::stub('email');

    expect($field)->toBeInstanceOf(StubField::class)
        ->and($field->getName())->toBe('email')
        ->and($field->getLabel())->toBe('Email');
});

it('rejects classes that do not extend Field on register', function (): void {
    FieldFactory::register('bad', stdClass::class);
})->throws(InvalidArgumentException::class, 'must extend Arqel\\Fields\\Field');

it('returns false from hasType for unknown types', function (): void {
    expect(FieldFactory::hasType('missing'))->toBeFalse();
});

it('supports macros that compose existing fields', function (): void {
    FieldFactory::register('stub', StubField::class);
    FieldFactory::macro('verifiedStub', fn (string $name) => FieldFactory::stub($name)->readonly());

    expect(FieldFactory::hasMacro('verifiedStub'))->toBeTrue();

    $field = FieldFactory::verifiedStub('email');

    expect($field)->toBeInstanceOf(StubField::class)
        ->and($field->isReadonly())->toBeTrue();
});

it('prefers macros over registered types when names collide', function (): void {
    FieldFactory::register('stub', StubField::class);
    FieldFactory::macro('stub', fn (string $name) => (new StubField($name))->label('FROM MACRO'));

    $field = FieldFactory::stub('email');

    expect($field->getLabel())->toBe('FROM MACRO');
});

it('throws BadMethodCallException for unknown calls', function (): void {
    FieldFactory::missing('email');
})->throws(BadMethodCallException::class, 'is not registered');

it('flush clears both registry and macros', function (): void {
    FieldFactory::register('stub', StubField::class);
    FieldFactory::macro('shortcut', fn () => null);

    FieldFactory::flush();

    expect(FieldFactory::hasType('stub'))->toBeFalse()
        ->and(FieldFactory::hasMacro('shortcut'))->toBeFalse();
});
