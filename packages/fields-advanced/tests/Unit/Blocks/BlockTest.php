<?php

declare(strict_types=1);

use Arqel\Fields\Field;
use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Blocks\Block;

/**
 * Concrete fixture block used by the BlockTest suite. Lives next to
 * the tests because it has no value outside the unit-test surface.
 */
final class FixtureTextBlock extends Block
{
    public static function type(): string
    {
        return 'text';
    }

    public static function label(): string
    {
        return 'Text';
    }

    public static function icon(): ?string
    {
        return 'type';
    }

    /**
     * @return array<int, Field>
     */
    public function schema(): array
    {
        return [new TextField('body')];
    }
}

/**
 * Fixture block that does NOT override `icon()` so we can assert
 * the abstract base default returns `null`.
 */
final class FixtureBareBlock extends Block
{
    public static function type(): string
    {
        return 'bare';
    }

    public static function label(): string
    {
        return 'Bare';
    }

    /**
     * @return array<int, Field>
     */
    public function schema(): array
    {
        return [new TextField('value')];
    }
}

it('returns the canonical toArray shape', function (): void {
    $payload = (new FixtureTextBlock)->toArray();

    expect(array_keys($payload))->toBe(['type', 'label', 'icon', 'schema'])
        ->and($payload['type'])->toBe('text')
        ->and($payload['label'])->toBe('Text')
        ->and($payload['icon'])->toBe('type');
});

it('serialises each schema entry as an array', function (): void {
    $schema = (new FixtureTextBlock)->toArray()['schema'];

    expect($schema)->toBeArray()
        ->and($schema)->toHaveCount(1)
        ->and($schema[0])->toBeArray()
        ->and($schema[0]['name'])->toBe('body')
        ->and($schema[0]['type'])->toBe('text');
});

it('returns null for icon when the subclass does not override icon()', function (): void {
    expect(FixtureBareBlock::icon())->toBeNull()
        ->and((new FixtureBareBlock)->toArray()['icon'])->toBeNull();
});

it('exposes type() and label() as static metadata', function (): void {
    expect(FixtureTextBlock::type())->toBe('text')
        ->and(FixtureTextBlock::label())->toBe('Text')
        ->and(FixtureTextBlock::icon())->toBe('type');
});

it('silently filters non-Field entries in the schema before serialising', function (): void {
    $block = new class extends Block
    {
        public static function type(): string
        {
            return 'mixed';
        }

        public static function label(): string
        {
            return 'Mixed';
        }

        /**
         * @return array<int, mixed>
         */
        public function schema(): array
        {
            return [
                new TextField('keep_me'),
                'not a field',
                42,
                null,
            ];
        }
    };

    $schema = $block->toArray()['schema'];

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['name'])->toBe('keep_me');
});
