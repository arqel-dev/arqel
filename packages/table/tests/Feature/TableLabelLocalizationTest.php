<?php

declare(strict_types=1);

use Arqel\Table\Filters\Constraints\TextConstraint;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TrashedFilter;
use Arqel\Table\Summaries\Summary;
use Arqel\Table\Summaries\SumSummary;
use Illuminate\Support\Facades\Lang;

afterEach(function (): void {
    app()->setLocale('en');
});

it('localizes TrashedFilter soft-delete option labels by active locale', function (): void {
    Lang::addLines(['trashed.with' => 'With deleted'], 'en', 'app');
    Lang::addLines(['trashed.with' => 'Com excluídos'], 'pt_BR', 'app');

    $filter = TrashedFilter::make()
        ->withLabel('app::trashed.with');

    app()->setLocale('pt_BR');
    $options = $filter->getTypeSpecificProps()['options'];

    expect($options)->toBe([
        // Framework defaults are plain literals: pass through unchanged.
        ['value' => 'without', 'label' => 'Without deleted'],
        // App override that is a translation key resolves in the locale.
        ['value' => 'with', 'label' => 'Com excluídos'],
        ['value' => 'only', 'label' => 'Only deleted'],
    ]);
});

it('localizes the TrashedFilter top-level label by active locale', function (): void {
    $filter = TrashedFilter::make();

    // English default is the original literal, preserving stability.
    expect($filter->toArray()['label'])->toBe('Trashed');

    app()->setLocale('pt_BR');
    expect($filter->toArray()['label'])->toBe('Excluídos');
});

it('localizes the base Filter label in toArray by active locale', function (): void {
    Lang::addLines(['filters.status' => 'Status'], 'en', 'app');
    Lang::addLines(['filters.status' => 'Situação'], 'pt_BR', 'app');

    $keyed = SelectFilter::make('status')->label('app::filters.status');
    $literal = SelectFilter::make('kind')->label('Kind');

    app()->setLocale('pt_BR');
    expect($keyed->toArray()['label'])->toBe('Situação');
    // A plain literal label passes through untranslated.
    expect($literal->toArray()['label'])->toBe('Kind');
});

it('localizes the Summary label in toArray by active locale', function (): void {
    Lang::addLines(['summaries.total' => 'Total'], 'en', 'app');
    Lang::addLines(['summaries.total' => 'Total geral'], 'pt_BR', 'app');

    $keyed = Summary::sum('amount')->label('app::summaries.total');
    $literal = Summary::sum('amount')->label('Sum');

    app()->setLocale('pt_BR');
    expect($keyed->toArray()['label'])->toBe('Total geral');
    expect($literal->toArray()['label'])->toBe('Sum');
});

it('leaves a null Summary label as null in toArray', function (): void {
    // Direct construction without a label leaves it null; the localize guard
    // must not coerce null into a string.
    $summary = new SumSummary('amount');

    expect($summary->getLabel())->toBeNull()
        ->and($summary->toArray()['label'])->toBeNull();
});

it('localizes the QueryBuilder Constraint label in toArray by active locale', function (): void {
    Lang::addLines(['constraints.title' => 'Title'], 'en', 'app');
    Lang::addLines(['constraints.title' => 'Título'], 'pt_BR', 'app');

    $keyed = TextConstraint::make('title')->label('app::constraints.title');
    $literal = TextConstraint::make('name')->label('Full name');

    app()->setLocale('pt_BR');
    expect($keyed->toArray()['label'])->toBe('Título');
    expect($literal->toArray()['label'])->toBe('Full name');
});
