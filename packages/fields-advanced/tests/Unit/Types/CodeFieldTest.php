<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\Types\CodeField;

it('exposes the correct type and component for CodeField', function (): void {
    $field = new CodeField('snippet');

    expect($field->getType())->toBe('code')
        ->and($field->getComponent())->toBe('CodeInput');
});

it('can be constructed via the FieldFactory code macro', function (): void {
    $field = FieldFactory::code('snippet');

    expect($field)->toBeInstanceOf(CodeField::class)
        ->and($field->getName())->toBe('snippet');
});

it('ships the canonical default state', function (): void {
    $props = (new CodeField('snippet'))->getTypeSpecificProps();

    expect($props)->toBe([
        'language' => 'plaintext',
        'theme' => null,
        'lineNumbers' => true,
        'wordWrap' => false,
        'tabSize' => 2,
        'readonly' => false,
        'minHeight' => null,
    ]);
});

it('persists the language via language()', function (): void {
    $field = (new CodeField('snippet'))->language('typescript');

    expect($field)->toBeInstanceOf(CodeField::class)
        ->and($field->getTypeSpecificProps()['language'])->toBe('typescript');
});

it('accepts both a theme string and null reset via theme()', function (): void {
    $set = (new CodeField('snippet'))->theme('github-dark');
    $reset = (new CodeField('snippet'))->theme('github-dark')->theme(null);

    expect($set->getTypeSpecificProps()['theme'])->toBe('github-dark')
        ->and($reset->getTypeSpecificProps()['theme'])->toBeNull();
});

it('toggles line numbers via lineNumbers()', function (): void {
    $defaultOn = new CodeField('snippet');
    $explicitOff = (new CodeField('snippet'))->lineNumbers(false);
    $reEnabled = (new CodeField('snippet'))->lineNumbers(false)->lineNumbers();

    expect($defaultOn->getTypeSpecificProps()['lineNumbers'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['lineNumbers'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['lineNumbers'])->toBeTrue();
});

it('toggles word wrap via wordWrap()', function (): void {
    $defaultOff = new CodeField('snippet');
    $explicitOn = (new CodeField('snippet'))->wordWrap(true);
    $explicitOff = (new CodeField('snippet'))->wordWrap(true)->wordWrap(false);

    expect($defaultOff->getTypeSpecificProps()['wordWrap'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['wordWrap'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['wordWrap'])->toBeFalse();
});

it('clamps tabSize to a minimum of 1', function (): void {
    $valid = (new CodeField('snippet'))->tabSize(4);
    $zero = (new CodeField('snippet'))->tabSize(0);
    $negative = (new CodeField('snippet'))->tabSize(-3);
    $one = (new CodeField('snippet'))->tabSize(1);

    expect($valid->getTypeSpecificProps()['tabSize'])->toBe(4)
        ->and($zero->getTypeSpecificProps()['tabSize'])->toBe(1)
        ->and($negative->getTypeSpecificProps()['tabSize'])->toBe(1)
        ->and($one->getTypeSpecificProps()['tabSize'])->toBe(1);
});

it('clamps minHeight to ≥0 and resets on null', function (): void {
    $valid = (new CodeField('snippet'))->minHeight(200);
    $negative = (new CodeField('snippet'))->minHeight(-50);
    $reset = (new CodeField('snippet'))->minHeight(200)->minHeight(null);

    expect($valid->getTypeSpecificProps()['minHeight'])->toBe(200)
        ->and($negative->getTypeSpecificProps()['minHeight'])->toBe(0)
        ->and($reset->getTypeSpecificProps()['minHeight'])->toBeNull();
});

it('exposes the readonly flag from the base Field via readonly()', function (): void {
    $defaultOff = new CodeField('snippet');
    $explicitOn = (new CodeField('snippet'))->readonly();
    $explicitOff = (new CodeField('snippet'))->readonly(true)->readonly(false);

    expect($defaultOff->getTypeSpecificProps()['readonly'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['readonly'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['readonly'])->toBeFalse();
});

it('returns all 7 keys from getTypeSpecificProps()', function (): void {
    $props = (new CodeField('snippet'))->getTypeSpecificProps();

    expect(array_keys($props))->toBe([
        'language',
        'theme',
        'lineNumbers',
        'wordWrap',
        'tabSize',
        'readonly',
        'minHeight',
    ]);
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new CodeField('snippet'))
        ->language('php')
        ->theme('github-dark')
        ->lineNumbers(false)
        ->wordWrap(true)
        ->tabSize(4)
        ->readonly()
        ->minHeight(320);

    expect($field->getTypeSpecificProps())->toBe([
        'language' => 'php',
        'theme' => 'github-dark',
        'lineNumbers' => false,
        'wordWrap' => true,
        'tabSize' => 4,
        'readonly' => true,
        'minHeight' => 320,
    ]);
});
