<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;

/** A duck-typed form-like object exposing getFields(). */
final class EF_FakeForm
{
    /** @param array<int, mixed> $fields */
    public function __construct(private array $fields) {}

    /** @return array<int, mixed> */
    public function getFields(): array
    {
        return $this->fields;
    }
}

/** Resource whose form() may or may not be set, with a flat fields(). */
final class EF_Resource extends Resource
{
    public static string $model = 'stdClass';

    /** @var array<int, mixed> */
    public static array $flat = ['flat-a'];

    public static mixed $formObject = null;

    public function fields(): array
    {
        return self::$flat;
    }

    public function form(): mixed
    {
        return self::$formObject;
    }
}

beforeEach(function (): void {
    EF_Resource::$flat = ['flat-a'];
    EF_Resource::$formObject = null;
});

it('returns the flat fields() when no form is declared', function (): void {
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});

it('returns the flat fields() when form() is not a form-like object', function (): void {
    EF_Resource::$formObject = 'not-a-form';
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});

it('returns form()->getFields() (re-indexed) when a form is declared', function (): void {
    EF_Resource::$formObject = new EF_FakeForm([2 => 'form-x', 5 => 'form-y']);
    expect((new EF_Resource)->effectiveFields())->toBe(['form-x', 'form-y']);
});

it('falls back to fields() when form()->getFields() is not an array', function (): void {
    EF_Resource::$formObject = new class
    {
        public function getFields(): mixed
        {
            return null;
        }
    };
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});
