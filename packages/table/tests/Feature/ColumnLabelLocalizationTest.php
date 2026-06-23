<?php

declare(strict_types=1);

use Arqel\Table\Columns\TextColumn;
use Illuminate\Support\Facades\Lang;

it('localizes a column label that is a translation key in toArray()', function (): void {
    Lang::addLines(['columns.name' => 'Name'], 'en', 'app');
    Lang::addLines(['columns.name' => 'Nome'], 'pt_BR', 'app');

    $column = TextColumn::make('whatever')->label('app::columns.name');

    app()->setLocale('en');
    expect($column->getLabel())->toBe('Name')
        ->and($column->toArray()['label'])->toBe('Name');

    app()->setLocale('pt_BR');
    expect($column->getLabel())->toBe('Nome')
        ->and($column->toArray()['label'])->toBe('Nome');
});

it('passes a plain literal column label through untranslated', function (): void {
    $column = TextColumn::make('full_name')->label('Full name');

    app()->setLocale('pt_BR');
    expect($column->getLabel())->toBe('Full name')
        ->and($column->toArray()['label'])->toBe('Full name');
});

it('passes an auto-derived literal column label through untranslated', function (): void {
    $column = TextColumn::make('full_name');

    app()->setLocale('pt_BR');
    expect($column->getLabel())->toBe('Full Name');
});
