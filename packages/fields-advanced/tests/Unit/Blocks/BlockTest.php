<?php

declare(strict_types=1);

use Arqel\Fields\Field;
use Arqel\Fields\Types\SelectField;
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

/**
 * Fixture block carrying a SelectField (with options) and a labelled,
 * placeheld TextField — the regression surface for #221.
 */
final class FixtureRichBlock extends Block
{
    public static function type(): string
    {
        return 'rich';
    }

    public static function label(): string
    {
        return 'Rich';
    }

    /**
     * @return array<int, Field>
     */
    public function schema(): array
    {
        return [
            (new TextField('title'))->label('My Title')->placeholder('Type here'),
            (new SelectField('status'))->options(['a' => 'Active', 'b' => 'Banned']),
        ];
    }
}

it('returns the canonical toArray shape', function (): void {
    $payload = (new FixtureTextBlock)->toArray();

    expect(array_keys($payload))->toBe(['type', 'label', 'icon', 'schema'])
        ->and($payload['type'])->toBe('text')
        ->and($payload['label'])->toBe('Text')
        ->and($payload['icon'])->toBe('type');
});

it('serialises each schema entry through the canonical FieldSchema shape (#221)', function (): void {
    $schema = (new FixtureTextBlock)->toArray()['schema'];

    expect($schema)->toBeArray()
        ->and($schema)->toHaveCount(1)
        ->and($schema[0])->toBeArray()
        ->and($schema[0]['name'])->toBe('body')
        ->and($schema[0]['type'])->toBe('text')
        // Rich FieldSchema, not the lossy {name,type} collapse pre-#221.
        ->and($schema[0])->toHaveKeys(['label', 'placeholder', 'props', 'validation']);
});

it('preserves a nested SelectField options + a TextField label/placeholder (#221)', function (): void {
    $schema = (new FixtureRichBlock)->toArray()['schema'];

    expect($schema)->toHaveCount(2)
        ->and($schema[0]['name'])->toBe('title')
        ->and($schema[0]['label'])->toBe('My Title')
        ->and($schema[0]['placeholder'])->toBe('Type here')
        ->and($schema[1]['name'])->toBe('status')
        ->and($schema[1]['type'])->toBe('select')
        ->and($schema[1]['props']['options'])->toBe(['a' => 'Active', 'b' => 'Banned']);
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
