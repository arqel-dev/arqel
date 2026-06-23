<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;
use Illuminate\Support\Facades\Lang;

it('localizes a label that is a translation key at serialization time', function (): void {
    Lang::addLines(['fields.name' => 'Name'], 'en', 'app');
    Lang::addLines(['fields.name' => 'Nome'], 'pt_BR', 'app');

    $field = (new StubField('whatever'))->label('app::fields.name');

    app()->setLocale('en');
    expect($field->getLabel())->toBe('Name');

    app()->setLocale('pt_BR');
    expect($field->getLabel())->toBe('Nome');
});

it('passes a plain literal label through untranslated', function (): void {
    $field = (new StubField('first_name'))->label('Given name');

    app()->setLocale('pt_BR');
    expect($field->getLabel())->toBe('Given name');
});

it('passes an auto-derived literal label through untranslated', function (): void {
    $field = new StubField('first_name');

    app()->setLocale('pt_BR');
    expect($field->getLabel())->toBe('First Name');
});
