<?php

declare(strict_types=1);

use Arqel\Fields\Field;
use Arqel\Fields\FieldFactory;
use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Blocks\Block;
use Arqel\FieldsAdvanced\Types\BuilderField;

/**
 * Fixture blocks scoped to the BuilderField test suite. Kept inline
 * to avoid polluting the package autoload with throw-away fixtures.
 */
final class FixtureBuilderTextBlock extends Block
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

final class FixtureBuilderImageBlock extends Block
{
    public static function type(): string
    {
        return 'image';
    }

    public static function label(): string
    {
        return 'Image';
    }

    public static function icon(): ?string
    {
        return 'image';
    }

    /**
     * @return array<int, Field>
     */
    public function schema(): array
    {
        return [new TextField('src'), new TextField('alt')];
    }
}

final class FixtureBuilderDuplicateTextBlock extends Block
{
    public static function type(): string
    {
        return 'text';
    }

    public static function label(): string
    {
        return 'Other Text';
    }

    /**
     * @return array<int, Field>
     */
    public function schema(): array
    {
        return [new TextField('content')];
    }
}

it('exposes the correct type and component for BuilderField', function (): void {
    $field = new BuilderField('content');

    expect($field->getType())->toBe('builder')
        ->and($field->getComponent())->toBe('BuilderInput');
});

it('can be constructed via BuilderField::make()', function (): void {
    $field = BuilderField::make('content');

    expect($field)->toBeInstanceOf(BuilderField::class)
        ->and($field->getName())->toBe('content');
});

it('can be constructed via the FieldFactory builder macro', function (): void {
    $field = FieldFactory::builder('content');

    expect($field)->toBeInstanceOf(BuilderField::class)
        ->and($field->getName())->toBe('content');
});

it('ships the canonical default state', function (): void {
    $props = (new BuilderField('content'))->getTypeSpecificProps();

    expect($props)->toBe([
        'blocks' => [],
        'minItems' => null,
        'maxItems' => null,
        'reorderable' => true,
        'collapsible' => true,
        'cloneable' => true,
        'itemLabel' => null,
    ]);
});

it('accepts a list of class-strings and keys them by Block::type()', function (): void {
    $field = (new BuilderField('content'))->blocks([
        FixtureBuilderTextBlock::class,
        FixtureBuilderImageBlock::class,
    ]);

    $blocks = $field->getTypeSpecificProps()['blocks'];

    expect(array_keys($blocks))->toBe(['text', 'image'])
        ->and($blocks['text']['label'])->toBe('Text')
        ->and($blocks['image']['label'])->toBe('Image');
});

it('preserves explicit keys when blocks() is given an associative map', function (): void {
    $field = (new BuilderField('content'))->blocks([
        'paragraph' => FixtureBuilderTextBlock::class,
    ]);

    $blocks = $field->getTypeSpecificProps()['blocks'];

    expect(array_keys($blocks))->toBe(['paragraph'])
        ->and($blocks['paragraph']['type'])->toBe('text');
});

it('accepts a mixed map of instances and class-strings', function (): void {
    $field = (new BuilderField('content'))->blocks([
        'text' => new FixtureBuilderTextBlock,
        'image' => FixtureBuilderImageBlock::class,
    ]);

    $blocks = $field->getTypeSpecificProps()['blocks'];

    expect(array_keys($blocks))->toBe(['text', 'image'])
        ->and($blocks['text']['icon'])->toBe('type')
        ->and($blocks['image']['icon'])->toBe('image');
});

it('silently filters non-Block entries from blocks()', function (): void {
    $field = (new BuilderField('content'))->blocks([
        FixtureBuilderTextBlock::class,
        'not a block class',
        42,
        null,
        new stdClass,
        FixtureBuilderImageBlock::class,
    ]);

    $blocks = $field->getTypeSpecificProps()['blocks'];

    expect(array_keys($blocks))->toBe(['text', 'image']);
});

it('throws InvalidArgumentException when two blocks share the same type', function (): void {
    (new BuilderField('content'))->blocks([
        FixtureBuilderTextBlock::class,
        FixtureBuilderDuplicateTextBlock::class,
    ]);
})->throws(InvalidArgumentException::class);

it('clamps minItems to ≥0 and persists positive values', function (): void {
    $negative = (new BuilderField('content'))->minItems(-5);
    $three = (new BuilderField('content'))->minItems(3);
    $zero = (new BuilderField('content'))->minItems(0);

    expect($negative->getTypeSpecificProps()['minItems'])->toBe(0)
        ->and($three->getTypeSpecificProps()['minItems'])->toBe(3)
        ->and($zero->getTypeSpecificProps()['minItems'])->toBe(0);
});

it('clamps maxItems to ≥1 and persists positive values', function (): void {
    $zero = (new BuilderField('content'))->maxItems(0);
    $negative = (new BuilderField('content'))->maxItems(-2);
    $five = (new BuilderField('content'))->maxItems(5);

    expect($zero->getTypeSpecificProps()['maxItems'])->toBe(1)
        ->and($negative->getTypeSpecificProps()['maxItems'])->toBe(1)
        ->and($five->getTypeSpecificProps()['maxItems'])->toBe(5);
});

it('throws InvalidArgumentException when maxItems < minItems', function (): void {
    (new BuilderField('content'))
        ->minItems(5)
        ->maxItems(3);
})->throws(InvalidArgumentException::class, 'maxItems must be >= minItems');

it('toggles reorderable, collapsible and cloneable via their setters', function (): void {
    $field = (new BuilderField('content'))
        ->reorderable(false)
        ->collapsible(false)
        ->cloneable(false);

    $props = $field->getTypeSpecificProps();

    expect($props['reorderable'])->toBeFalse()
        ->and($props['collapsible'])->toBeFalse()
        ->and($props['cloneable'])->toBeFalse();
});

it('persists the itemLabel template', function (): void {
    $field = (new BuilderField('content'))->itemLabel('{{type}}: {{title}}');

    expect($field->getTypeSpecificProps()['itemLabel'])->toBe('{{type}}: {{title}}');
});

it('returns all 7 keys from getTypeSpecificProps() with blocks keyed by type', function (): void {
    $field = (new BuilderField('content'))->blocks([
        FixtureBuilderTextBlock::class,
        FixtureBuilderImageBlock::class,
    ]);

    $props = $field->getTypeSpecificProps();

    expect(array_keys($props))->toBe([
        'blocks',
        'minItems',
        'maxItems',
        'reorderable',
        'collapsible',
        'cloneable',
        'itemLabel',
    ]);

    expect($props['blocks']['text'])->toMatchArray([
        'type' => 'text',
        'label' => 'Text',
        'icon' => 'type',
    ])->and($props['blocks']['text']['schema'][0]['name'])->toBe('body')
        ->and($props['blocks']['image']['schema'])->toHaveCount(2);
});
