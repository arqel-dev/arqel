<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\FieldsAdvancedServiceProvider;
use Arqel\FieldsAdvanced\Types\CodeField;
use Arqel\FieldsAdvanced\Types\MarkdownField;
use Arqel\FieldsAdvanced\Types\RepeaterField;
use Arqel\FieldsAdvanced\Types\RichTextField;

it('boots the FieldsAdvancedServiceProvider without errors', function (): void {
    $provider = app()->getProvider(FieldsAdvancedServiceProvider::class);

    expect($provider)->toBeInstanceOf(FieldsAdvancedServiceProvider::class);
});

it('autoloads classes from the Arqel\\FieldsAdvanced namespace', function (): void {
    expect(class_exists(RichTextField::class))->toBeTrue()
        ->and(class_exists(FieldsAdvancedServiceProvider::class))->toBeTrue();
});

it('registers the richText macro on the FieldFactory', function (): void {
    expect(FieldFactory::hasType('richText'))->toBeTrue();

    $field = FieldFactory::richText('content');

    expect($field)->toBeInstanceOf(RichTextField::class)
        ->and($field->getName())->toBe('content');
});

it('registers the markdown macro on the FieldFactory', function (): void {
    expect(FieldFactory::hasType('markdown'))->toBeTrue();

    $field = FieldFactory::markdown('content');

    expect($field)->toBeInstanceOf(MarkdownField::class)
        ->and($field->getName())->toBe('content');
});

it('registers the code macro on the FieldFactory', function (): void {
    expect(FieldFactory::hasType('code'))->toBeTrue();

    $field = FieldFactory::code('content');

    expect($field)->toBeInstanceOf(CodeField::class)
        ->and($field->getName())->toBe('content');
});

it('registers the repeater macro on the FieldFactory', function (): void {
    expect(FieldFactory::hasType('repeater'))->toBeTrue();

    $field = FieldFactory::repeater('addresses');

    expect($field)->toBeInstanceOf(RepeaterField::class)
        ->and($field->getName())->toBe('addresses');
});
