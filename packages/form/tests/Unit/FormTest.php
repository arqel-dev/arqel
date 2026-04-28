<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Tests\Fixtures\StubLayout;

it('builds an empty form with sane defaults', function (): void {
    $form = Form::make();

    expect($form->getSchema())->toBe([])
        ->and($form->getColumns())->toBe(1)
        ->and($form->getModel())->toBeNull()
        ->and($form->isInline())->toBeFalse()
        ->and($form->isDisabled())->toBeFalse();
});

it('chains fluent setters', function (): void {
    $form = Form::make()
        ->columns(2)
        ->model(Illuminate\Database\Eloquent\Model::class)
        ->inline()
        ->disabled();

    expect($form->getColumns())->toBe(2)
        ->and($form->getModel())->toBe(Illuminate\Database\Eloquent\Model::class)
        ->and($form->isInline())->toBeTrue()
        ->and($form->isDisabled())->toBeTrue();
});

it('clamps columns to a minimum of 1', function (): void {
    $form = Form::make()->columns(0);

    expect($form->getColumns())->toBe(1);
});

it('preserves schema as a flat array of fields and components', function (): void {
    $name = new TextField('name');
    $email = new TextField('email');
    $section = new StubLayout('Personal', [$name, $email]);

    $form = Form::make()->schema([$section]);

    expect($form->getSchema())->toHaveCount(1)
        ->and($form->getSchema()[0])->toBe($section);
});

it('flattens fields out of nested layout components', function (): void {
    $name = new TextField('name');
    $email = new TextField('email');
    $bio = new TextField('bio');

    $inner = new StubLayout('Contact', [$email]);
    $outer = new StubLayout('Identity', [$name, $inner, $bio]);

    $form = Form::make()->schema([$outer]);

    $flat = $form->getFields();

    expect($flat)->toHaveCount(3)
        ->and($flat[0]->getName())->toBe('name')
        ->and($flat[1]->getName())->toBe('email')
        ->and($flat[2]->getName())->toBe('bio');
});

it('serialises fields and layout components with their kind', function (): void {
    $name = new TextField('name');
    $section = new StubLayout('Personal', [new TextField('email')]);

    $form = Form::make()
        ->columns(2)
        ->inline()
        ->schema([$name, $section]);

    $payload = $form->toArray();

    expect($payload['columns'])->toBe(2)
        ->and($payload['inline'])->toBeTrue()
        ->and($payload['schema'])->toBe([
            ['kind' => 'field', 'name' => 'name', 'type' => 'text'],
            [
                'kind' => 'layout',
                'type' => 'stub',
                'component' => 'StubLayout',
                'columnSpan' => 1,
                'props' => ['label' => 'Personal', 'count' => 1],
            ],
        ]);
});

it('returns null model in payload when none is configured', function (): void {
    $payload = Form::make()->toArray();

    expect($payload['model'])->toBeNull();
});
