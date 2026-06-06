<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\EmailField;
use Arqel\Fields\Types\NumberField;
use Arqel\Fields\Types\TextField;
use Arqel\Fields\Types\UrlField;
use Illuminate\Support\Facades\Validator;

/**
 * Regression guard for issue #80 — optional typed fields rejecting an
 * explicit JSON `null`.
 *
 * The framework's own typed React inputs (DateInput, NumberInput,
 * SelectInput, etc.) emit `null` when cleared. An optional typed field
 * shipped only its bare type rule (`date`, `email`, `numeric`, ...),
 * and every Laravel type rule rejects an explicit `null` — so clearing
 * a non-required field 422'd on save. An optional typed field must now
 * accept `null` by default while still enforcing the type for non-null
 * values.
 */
it('prepends nullable to an optional DateTimeField and still enforces the type', function (): void {
    $field = new DateTimeField('published_at');

    expect($field->getValidationRules())->toContain('nullable')
        ->and($field->getValidationRules())->toContain('date');

    $rules = ['published_at' => $field->getValidationRules()];

    expect(Validator::make(['published_at' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['published_at' => '2026-01-01 10:00:00'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['published_at' => 'not-a-date'], $rules)->fails())->toBeTrue();
});

it('prepends nullable to an optional EmailField and still enforces the type', function (): void {
    $field = new EmailField('email');

    expect($field->getValidationRules())->toContain('nullable', 'email');

    $rules = ['email' => $field->getValidationRules()];

    expect(Validator::make(['email' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['email' => 'jane@example.com'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['email' => 'not-an-email'], $rules)->fails())->toBeTrue();
});

it('prepends nullable to an optional UrlField', function (): void {
    $field = new UrlField('homepage');

    expect($field->getValidationRules())->toContain('nullable', 'url');

    $rules = ['homepage' => $field->getValidationRules()];

    expect(Validator::make(['homepage' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['homepage' => 'not a url'], $rules)->fails())->toBeTrue();
});

it('places nullable before the type rule', function (): void {
    $rules = (new DateTimeField('published_at'))->getValidationRules();

    expect(array_search('nullable', $rules, true))
        ->toBeLessThan(array_search('date', $rules, true));
});

it('does not add nullable to a required field and still rejects null', function (): void {
    $field = (new TextField('title'))->required()->maxLength(255);

    expect($field->getValidationRules())->not->toContain('nullable')
        ->and($field->getValidationRules())->toContain('required');

    $rules = ['title' => $field->getValidationRules()];

    expect(Validator::make(['title' => null], $rules)->fails())->toBeTrue()
        ->and(Validator::make([], $rules)->fails())->toBeTrue();
});

it('does not duplicate nullable when the field declares it explicitly', function (): void {
    $field = (new DateTimeField('published_at'))->nullable();

    $rules = $field->getValidationRules();
    $nullableCount = count(array_filter($rules, fn ($rule): bool => $rule === 'nullable'));

    expect($nullableCount)->toBe(1);
});

it('accepts null for an optional NumberField but enforces type', function (): void {
    $field = new NumberField('age');

    expect($field->getValidationRules())->toContain('nullable', 'numeric');

    $rules = ['age' => $field->getValidationRules()];

    expect(Validator::make(['age' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['age' => 'abc'], $rules)->fails())->toBeTrue();
});

it('rejects null for a required NumberField', function (): void {
    $field = (new NumberField('age'))->required();

    expect($field->getValidationRules())->not->toContain('nullable');

    $rules = ['age' => $field->getValidationRules()];

    expect(Validator::make(['age' => null], $rules)->fails())->toBeTrue()
        ->and(Validator::make([], $rules)->fails())->toBeTrue();
});

it('accepts null for an optional BooleanField but rejects non-boolean', function (): void {
    $field = new BooleanField('is_active');

    expect($field->getValidationRules())->toContain('nullable', 'boolean');

    $rules = ['is_active' => $field->getValidationRules()];

    expect(Validator::make(['is_active' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['is_active' => true], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['is_active' => 'not-a-bool'], $rules)->fails())->toBeTrue();
});

it('rejects null and absence for a required BooleanField', function (): void {
    $field = (new BooleanField('is_active'))->required();

    expect($field->getValidationRules())->not->toContain('nullable')
        ->and($field->getValidationRules())->toContain('required', 'boolean');

    $rules = ['is_active' => $field->getValidationRules()];

    expect(Validator::make(['is_active' => null], $rules)->fails())->toBeTrue()
        ->and(Validator::make([], $rules)->fails())->toBeTrue();
});

it('does not add nullable to a field that has no rules at all', function (): void {
    $field = new StubField('notes');

    expect($field->getValidationRules())->toBe([]);
});
