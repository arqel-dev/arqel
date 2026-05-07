<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Fieldset;
use Arqel\Form\Layout\Section;

it('Form::toArray emits children inside Section as schema entries', function (): void {
    $form = Form::make()
        ->schema([
            Section::make('Content')
                ->schema([
                    new TextField('title'),
                    new TextField('body'),
                ]),
            Section::make('Meta')
                ->schema([
                    new TextField('status'),
                ]),
        ]);

    $array = $form->toArray();

    expect($array['schema'])->toHaveCount(2);

    $content = $array['schema'][0];
    expect($content['kind'])->toBe('layout')
        ->and($content['type'])->toBe('section')
        ->and($content['props']['heading'])->toBe('Content')
        ->and($content['schema'])->toHaveCount(2)
        ->and($content['schema'][0])->toMatchArray([
            'kind' => 'field',
            'name' => 'title',
            'type' => 'text',
        ])
        ->and($content['schema'][1])->toMatchArray([
            'kind' => 'field',
            'name' => 'body',
            'type' => 'text',
        ]);

    $meta = $array['schema'][1];
    expect($meta['schema'])->toHaveCount(1)
        ->and($meta['schema'][0]['name'])->toBe('status');
});

it('Form::toArray descends nested layouts (Section > Fieldset > Field)', function (): void {
    $form = Form::make()
        ->schema([
            Section::make('Outer')
                ->schema([
                    Fieldset::make('Inner block')
                        ->schema([
                            new TextField('inner'),
                        ]),
                ]),
        ]);

    $array = $form->toArray();

    expect($array['schema'][0]['schema'][0]['kind'])->toBe('layout')
        ->and($array['schema'][0]['schema'][0]['type'])->toBe('fieldset')
        ->and($array['schema'][0]['schema'][0]['schema'][0])->toMatchArray([
            'kind' => 'field',
            'name' => 'inner',
            'type' => 'text',
        ]);
});

it('Form::toArray emits empty schema array for layouts without children', function (): void {
    $form = Form::make()
        ->schema([
            Section::make('Empty')->schema([]),
        ]);

    $array = $form->toArray();

    expect($array['schema'][0]['schema'])->toBe([]);
});
