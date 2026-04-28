<?php

declare(strict_types=1);

use Arqel\Fields\Types\EmailField;
use Arqel\Fields\Types\PasswordField;
use Arqel\Fields\Types\SlugField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Fields\Types\UrlField;

it('exposes the correct type and component for TextField', function (): void {
    $field = new TextField('name');

    expect($field->getType())->toBe('text')
        ->and($field->getComponent())->toBe('TextInput');
});

it('serializes Text constraints into type-specific props', function (): void {
    $field = (new TextField('name'))
        ->maxLength(255)
        ->minLength(2)
        ->pattern('^[A-Za-z]+$')
        ->autocomplete('given-name')
        ->mask('999.999.999-99');

    expect($field->getTypeSpecificProps())->toBe([
        'maxLength' => 255,
        'minLength' => 2,
        'pattern' => '^[A-Za-z]+$',
        'autocomplete' => 'given-name',
        'mask' => '999.999.999-99',
    ]);
});

it('omits unset Text props from the serialised payload', function (): void {
    expect((new TextField('name'))->getTypeSpecificProps())->toBe([]);
});

it('inherits Text props on TextareaField and adds rows/cols', function (): void {
    $field = (new TextareaField('bio'))
        ->maxLength(500)
        ->rows(6)
        ->cols(40);

    expect($field->getType())->toBe('textarea')
        ->and($field->getComponent())->toBe('TextareaInput')
        ->and($field->getTypeSpecificProps())->toBe([
            'maxLength' => 500,
            'rows' => 6,
            'cols' => 40,
        ]);
});

it('declares the email default rule on EmailField', function (): void {
    $field = new EmailField('email');

    expect($field->getType())->toBe('email')
        ->and($field->getComponent())->toBe('EmailInput')
        ->and($field->getDefaultRules())->toBe(['email']);
});

it('declares the url default rule on UrlField', function (): void {
    $field = new UrlField('homepage');

    expect($field->getType())->toBe('url')
        ->and($field->getComponent())->toBe('UrlInput')
        ->and($field->getDefaultRules())->toBe(['url']);
});

it('exposes the revealable flag on PasswordField', function (): void {
    $field = (new PasswordField('password'))->revealable();

    expect($field->getType())->toBe('password')
        ->and($field->getComponent())->toBe('PasswordInput')
        ->and($field->isRevealable())->toBeTrue()
        ->and($field->getTypeSpecificProps())->toBe(['revealable' => true]);
});

it('serialises SlugField with fromField and the default separator', function (): void {
    $field = (new SlugField('slug'))->fromField('title');

    expect($field->getType())->toBe('slug')
        ->and($field->getComponent())->toBe('SlugInput')
        ->and($field->getTypeSpecificProps())->toBe([
            'fromField' => 'title',
            'separator' => '-',
        ]);
});

it('honours a custom separator on SlugField', function (): void {
    $field = (new SlugField('slug'))->fromField('title')->separator('_');

    expect($field->getTypeSpecificProps()['separator'])->toBe('_');
});
