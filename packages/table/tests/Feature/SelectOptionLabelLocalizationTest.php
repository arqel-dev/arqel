<?php

declare(strict_types=1);

use Arqel\Table\Columns\SelectColumn;
use Arqel\Table\Filters\SelectFilter;
use Illuminate\Support\Facades\Lang;

afterEach(function (): void {
    app()->setLocale('en');
});

it('localizes SelectColumn static option labels by active locale', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $column = SelectColumn::make('status')->options([
        'draft' => 'app::status.draft',
        // A plain literal label passes through untranslated.
        'published' => 'Published',
    ]);

    app()->setLocale('en');
    expect($column->resolveOptions())->toBe([
        'draft' => 'Draft',
        'published' => 'Published',
    ]);

    app()->setLocale('pt_BR');
    expect($column->resolveOptions())->toBe([
        'draft' => 'Rascunho',
        'published' => 'Published',
    ])->and($column->toArray()['options'])->toBe([
        'draft' => 'Rascunho',
        'published' => 'Published',
    ]);
});

it('localizes SelectColumn Closure option labels at resolve time', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $column = SelectColumn::make('status')->options(fn () => ['draft' => 'app::status.draft']);

    app()->setLocale('pt_BR');
    expect($column->resolveOptions())->toBe(['draft' => 'Rascunho']);
});

it('localizes SelectFilter option labels in the normalized payload', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $filter = SelectFilter::make('status')->options([
        'draft' => 'app::status.draft',
        'published' => 'Published',
    ]);

    app()->setLocale('pt_BR');
    $options = $filter->getTypeSpecificProps()['options'];

    expect($options)->toBe([
        ['value' => 'draft', 'label' => 'Rascunho'],
        ['value' => 'published', 'label' => 'Published'],
    ]);
});
