<?php

declare(strict_types=1);

use Arqel\Fields\Types\SelectField;
use Illuminate\Support\Facades\Lang;

afterEach(function (): void {
    app()->setLocale('en');
});

it('localizes static option labels that are translation keys', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $field = (new SelectField('status'))->options([
        'draft' => 'app::status.draft',
        // Plain literal passes through untranslated.
        'published' => 'Published',
    ]);

    app()->setLocale('en');
    expect($field->resolveOptions())->toBe([
        'draft' => 'Draft',
        'published' => 'Published',
    ]);

    app()->setLocale('pt_BR');
    expect($field->resolveOptions())->toBe([
        'draft' => 'Rascunho',
        'published' => 'Published',
    ]);
});

it('localizes Closure option labels at resolve time', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $field = (new SelectField('status'))->options(
        fn () => ['draft' => 'app::status.draft'],
    );

    app()->setLocale('pt_BR');
    expect($field->resolveOptions())->toBe(['draft' => 'Rascunho']);
});

it('surfaces the localized option labels in getTypeSpecificProps()', function (): void {
    Lang::addLines(['status.draft' => 'Draft'], 'en', 'app');
    Lang::addLines(['status.draft' => 'Rascunho'], 'pt_BR', 'app');

    $field = (new SelectField('status'))->options(['draft' => 'app::status.draft']);

    app()->setLocale('pt_BR');
    expect($field->getTypeSpecificProps()['options'])->toBe(['draft' => 'Rascunho']);
});

it('passes a plain literal option label through untranslated', function (): void {
    $field = (new SelectField('status'))->options(['draft' => 'Draft']);

    app()->setLocale('pt_BR');
    expect($field->resolveOptions())->toBe(['draft' => 'Draft']);
});
