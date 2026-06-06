<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;

/**
 * Regression coverage for #115: a field whose only guard is an
 * enclosing layout `canSee()`/`visibleIf()` must be pruned from both
 * the serialised payload (render) and the flattened field list (the
 * single source for render + write), so it never leaks.
 */
it('toArray prunes a layout denied by canSee plus its fields', function (): void {
    $form = Form::make()->schema([
        Section::make('Public')->schema([
            new TextField('visible'),
        ]),
        Section::make('Secret')
            ->canSee(fn () => false)
            ->schema([
                new TextField('secret'),
            ]),
    ]);

    $schema = $form->toArray()['schema'];

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['props']['heading'])->toBe('Public')
        ->and($schema[0]['schema'][0]['name'])->toBe('visible');
});

it('toArray prunes a layout denied by visibleIf', function (): void {
    $form = Form::make()->schema([
        Section::make('Secret')
            ->visibleIf(fn () => false)
            ->schema([new TextField('secret')]),
    ]);

    expect($form->toArray()['schema'])->toBe([]);
});

it('getFields excludes fields under a hidden layout', function (): void {
    $form = Form::make()->schema([
        Section::make('Public')->schema([new TextField('visible')]),
        Section::make('Secret')
            ->canSee(fn () => false)
            ->schema([new TextField('secret')]),
    ]);

    $names = array_map(fn ($f) => $f->getName(), $form->getFields());

    expect($names)->toBe(['visible'])
        ->and($names)->not->toContain('secret');
});

it('default layout with no canSee stays fully visible (no regression)', function (): void {
    $form = Form::make()->schema([
        Section::make('Profile')->schema([
            new TextField('name'),
            new TextField('email'),
        ]),
    ]);

    $names = array_map(fn ($f) => $f->getName(), $form->getFields());
    $schema = $form->toArray()['schema'];

    expect($names)->toBe(['name', 'email'])
        ->and($schema)->toHaveCount(1)
        ->and($schema[0]['schema'])->toHaveCount(2);
});

it('threads the record into the visibility predicate', function (): void {
    $record = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $attributes = ['locked' => true];

        protected $guarded = [];
    };

    $form = Form::make()->schema([
        Section::make('Editable')
            ->canSee(fn ($r) => $r === null || ! $r->locked)
            ->schema([new TextField('payload')]),
    ]);

    // With a locked record, the section is hidden.
    expect($form->getFields($record))->toBe([])
        ->and($form->toArray($record)['schema'])->toBe([]);

    // On create (no record), the predicate's null-guard keeps it visible.
    expect(array_map(fn ($f) => $f->getName(), $form->getFields()))
        ->toBe(['payload']);
});

it('prunes nested fields when an outer layout is hidden', function (): void {
    $form = Form::make()->schema([
        Section::make('Outer')
            ->canSee(fn () => false)
            ->schema([
                Section::make('Inner')->schema([
                    new TextField('deep'),
                ]),
            ]),
    ]);

    expect($form->getFields())->toBe([])
        ->and($form->toArray()['schema'])->toBe([]);
});
