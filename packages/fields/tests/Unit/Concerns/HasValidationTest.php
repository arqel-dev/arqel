<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;

it('adds required idempotently', function (): void {
    $field = (new StubField('email'))->required()->required();

    expect($field->getValidationRules())->toContain('required');
});

it('does not add required when conditional is false', function (): void {
    $field = (new StubField('email'))->required(false);

    expect($field->getValidationRules())->not->toContain('required');
});

it('drops a previously-added required when called with false', function (): void {
    $field = (new StubField('email'))->required()->required(false);

    expect($field->getValidationRules())->not->toContain('required');
});

it('resolves Closure-wrapped required rules', function (): void {
    $field = (new StubField('email'))->required(fn () => 'required');

    expect($field->getValidationRules())->toContain('required');
});

it('records nullable, maxLength, and minLength as separate rules', function (): void {
    $field = (new StubField('name'))->nullable()->maxLength(255)->minLength(2);

    expect($field->getValidationRules())->toContain('nullable', 'max:255', 'min:2');
});

it('emits a unique rule with field name as default column', function (): void {
    $field = (new StubField('email'))->unique('users');

    expect($field->getValidationRules())->toContain('unique:users,email');
});

it('honours an explicit unique column and ignorable id', function (): void {
    $field = (new StubField('slug'))->unique('posts', 'slug', 42);

    expect($field->getValidationRules())->toContain('unique:posts,slug,42');
});

it('appends rules from the rules array helper', function (): void {
    $field = (new StubField('age'))->rules(['min:0', 'max:120']);

    expect($field->getValidationRules())->toContain('min:0', 'max:120');
});

it('records a custom validation message for a string rule', function (): void {
    $field = (new StubField('email'))->rule('required', 'Email is mandatory.');

    expect($field->getValidationMessages())->toBe(['required' => 'Email is mandatory.'])
        ->and($field->getValidationRules())->toContain('required');
});

it('overrides a validation message via validationMessage()', function (): void {
    $field = (new StubField('email'))
        ->required()
        ->validationMessage('required', 'Please fill the email field.');

    expect($field->getValidationMessages()['required'])->toBe('Please fill the email field.');
});

it('records a per-field validation attribute name', function (): void {
    $field = (new StubField('email'))->validationAttribute('email address');

    expect($field->getValidationAttribute())->toBe('email address');
});

it('emits requiredIf with the other field and value', function (): void {
    $field = (new StubField('email'))->requiredIf('account_type', 'business');

    expect($field->getValidationRules())->toContain('required_if:account_type,business');
});

it('emits requiredWith and requiredWithout with comma-joined fields', function (): void {
    $with = (new StubField('email'))->requiredWith(['first_name', 'last_name']);
    $without = (new StubField('email'))->requiredWithout('phone');

    expect($with->getValidationRules())->toContain('required_with:first_name,last_name')
        ->and($without->getValidationRules())->toContain('required_without:phone');
});

it('preserves rule objects verbatim when added via rule()', function (): void {
    $rule = new class
    {
        public function __toString(): string
        {
            return 'custom';
        }
    };

    $field = (new StubField('password'))->rule($rule);

    expect($field->getValidationRules())->toContain($rule);
});

it('exposes default rules from getDefaultRules() ahead of fluent rules', function (): void {
    $field = new class('email') extends StubField
    {
        public function getDefaultRules(): array
        {
            return ['email'];
        }
    };

    $field->required();

    expect($field->getValidationRules())->toBe(['email', 'required']);
});

it('dedupes default rules against fluent rules by base name', function (): void {
    $field = new class('age') extends StubField
    {
        public function getDefaultRules(): array
        {
            return ['min:0'];
        }
    };

    $field->minLength(5);

    expect($field->getValidationRules())->toBe(['min:5']);
});
