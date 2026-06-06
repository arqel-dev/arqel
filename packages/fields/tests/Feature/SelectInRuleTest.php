<?php

declare(strict_types=1);

use Arqel\Fields\Types\MultiSelectField;
use Arqel\Fields\Types\RadioField;
use Arqel\Fields\Types\SelectField;
use Illuminate\Support\Facades\Validator;

it('derives an in: rule from static option keys on SelectField', function (): void {
    $field = (new SelectField('status'))->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ]);

    expect($field->getDefaultRules())->toBe(['in:draft,published,archived'])
        ->and($field->getValidationRules())->toContain('in:draft,published,archived');
});

it('rejects an out-of-range value and accepts a valid one for a closed select', function (): void {
    $field = (new SelectField('status'))->options([
        'draft' => 'Draft',
        'published' => 'Published',
    ]);

    $rules = ['status' => $field->getValidationRules()];

    expect(Validator::make(['status' => 'totally-not-valid'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['status' => 'draft'], $rules)->passes())->toBeTrue();
});

it('emits no in: rule when the select allows custom values', function (): void {
    $field = (new SelectField('status'))
        ->options(['draft' => 'Draft', 'published' => 'Published'])
        ->allowCustomValues();

    expect($field->getDefaultRules())->toBe([]);

    $rules = ['status' => $field->getValidationRules()];
    expect(Validator::make(['status' => 'anything'], $rules)->passes())->toBeTrue();
});

it('emits no in: rule when the select is creatable', function (): void {
    $field = (new SelectField('status'))
        ->options(['draft' => 'Draft'])
        ->creatable();

    expect($field->getDefaultRules())->toBe([]);
});

it('emits no in: rule and does not crash for dynamic (closure) options', function (): void {
    $field = (new SelectField('cat'))->options(fn () => [1 => 'A', 2 => 'B']);

    expect($field->getDefaultRules())->toBe([]);
});

it('emits no in: rule and does not crash for relationship options', function (): void {
    $field = (new SelectField('cat'))->optionsRelationship('category', 'name');

    expect($field->getDefaultRules())->toBe([]);
});

it('emits no in: rule when no options are configured', function (): void {
    $field = new SelectField('status');

    expect($field->getDefaultRules())->toBe([]);
});

it('merges the derived in: rule additively with user-supplied rules', function (): void {
    $field = (new SelectField('status'))
        ->options(['draft' => 'Draft', 'published' => 'Published'])
        ->rules(['required']);

    $rules = $field->getValidationRules();

    expect($rules)->toContain('required')
        ->and($rules)->toContain('in:draft,published');
});

it('derives an in: rule on RadioField (single-value select)', function (): void {
    $field = (new RadioField('size'))->options(['s' => 'S', 'l' => 'L']);

    expect($field->getDefaultRules())->toBe(['in:s,l']);

    $rules = ['size' => $field->getValidationRules()];
    expect(Validator::make(['size' => 'xl'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['size' => 's'], $rules)->passes())->toBeTrue();
});

it('emits array + nested *.in rules for a multiple select', function (): void {
    $field = (new MultiSelectField('tags'))->options([
        'php' => 'PHP',
        'js' => 'JS',
        'go' => 'Go',
    ]);

    expect($field->getDefaultRules())->toBe(['array'])
        ->and($field->getNestedValidationRules())->toBe([
            'tags.*' => ['in:php,js,go'],
        ]);
});

it('validates multiple-select elements against the option set', function (): void {
    $field = (new MultiSelectField('tags'))->options([
        'php' => 'PHP',
        'js' => 'JS',
    ]);

    $rules = ['tags' => $field->getValidationRules()] + $field->getNestedValidationRules();

    expect(Validator::make(['tags' => ['php', 'nope']], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['tags' => ['php', 'js']], $rules)->passes())->toBeTrue();
});

it('emits no nested rule for a multiple select that allows custom values', function (): void {
    $field = (new MultiSelectField('tags'))
        ->options(['php' => 'PHP'])
        ->allowCustomValues();

    expect($field->getNestedValidationRules())->toBe([])
        ->and($field->getDefaultRules())->toBe(['array']);
});
