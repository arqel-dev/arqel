<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\Form\FieldRulesExtractor;

beforeEach(function (): void {
    $this->extractor = new FieldRulesExtractor;
});

it('aggregates validation rules per field name', function (): void {
    $fields = [
        (new TextField('name'))->required()->rule('max:255'),
        (new TextField('email'))->required()->rule('email'),
        (new TextField('bio'))->nullable(),
    ];

    expect($this->extractor->extract($fields))->toBe([
        'name' => ['required', 'max:255'],
        'email' => ['required', 'email'],
        'bio' => ['nullable'],
    ]);
});

it('ignores non-Field entries gracefully', function (): void {
    $fields = [
        new TextField('name'),
        'not-a-field',
        42,
        new TextField('email'),
    ];

    expect($this->extractor->extract($fields))->toHaveKeys(['name', 'email']);
});

it('extractMessages namespaces per field.rule', function (): void {
    $field = (new TextField('email'))
        ->required()
        ->rule('email')
        ->validationMessage('email', 'Please provide a valid email address.');

    expect($this->extractor->extractMessages([$field]))
        ->toBe(['email.email' => 'Please provide a valid email address.']);
});

it('extractMessages returns [] when no custom messages are set', function (): void {
    expect($this->extractor->extractMessages([new TextField('name')]))->toBe([]);
});

it('extractAttributes returns the override when set', function (): void {
    $field = (new TextField('email'))->validationAttribute('e-mail address');

    expect($this->extractor->extractAttributes([$field]))->toBe(['email' => 'e-mail address']);
});

it('extractAttributes returns [] when no overrides are set', function (): void {
    expect($this->extractor->extractAttributes([new TextField('name')]))->toBe([]);
});
