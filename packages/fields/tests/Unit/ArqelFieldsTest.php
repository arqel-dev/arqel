<?php

declare(strict_types=1);

use Arqel\Fields\ArqelFields;
use Arqel\Fields\FieldFactory;
use Arqel\Fields\Tests\Fixtures\StubField;

beforeEach(function (): void {
    FieldFactory::flush();
});

afterEach(function (): void {
    FieldFactory::flush();
});

it('reports an empty inventory when nothing is registered', function (): void {
    expect(ArqelFields::skill())->toBe([
        'types' => [],
        'macros' => [],
    ]);
});

it('reports registered types via FieldFactory', function (): void {
    FieldFactory::register('stub', StubField::class);
    FieldFactory::register('alias', StubField::class);

    $skill = ArqelFields::skill();

    expect($skill['types'])->toBe([
        'stub' => StubField::class,
        'alias' => StubField::class,
    ]);
});

it('reports macros sorted alphabetically by name only', function (): void {
    FieldFactory::macro('zulu', fn () => null);
    FieldFactory::macro('alpha', fn () => null);
    FieldFactory::macro('mike', fn () => null);

    $skill = ArqelFields::skill();

    expect($skill['macros'])->toBe(['alpha', 'mike', 'zulu']);
});

it('the skill payload is JSON-serialisable', function (): void {
    FieldFactory::register('stub', StubField::class);
    FieldFactory::macro('priceBRL', fn () => null);

    $json = json_encode(ArqelFields::skill(), JSON_THROW_ON_ERROR);

    expect($json)->toBeString()
        ->and($json)->toContain('"stub":')
        ->and($json)->toContain('"priceBRL"');
});
