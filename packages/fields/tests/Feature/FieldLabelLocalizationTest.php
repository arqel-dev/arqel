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

it('localizes a placeholder that is a translation key at serialization time', function (): void {
    Lang::addLines(['fields.email_ph' => 'you@example.com'], 'en', 'app');
    Lang::addLines(['fields.email_ph' => 'voce@exemplo.com'], 'pt_BR', 'app');

    $field = (new StubField('email'))->placeholder('app::fields.email_ph');

    app()->setLocale('en');
    expect($field->getPlaceholder())->toBe('you@example.com');

    app()->setLocale('pt_BR');
    expect($field->getPlaceholder())->toBe('voce@exemplo.com');
});

it('localizes helper text that is a translation key at serialization time', function (): void {
    Lang::addLines(['fields.email_help' => 'Your work email'], 'en', 'app');
    Lang::addLines(['fields.email_help' => 'Seu e-mail de trabalho'], 'pt_BR', 'app');

    $field = (new StubField('email'))->helperText('app::fields.email_help');

    app()->setLocale('en');
    expect($field->getHelperText())->toBe('Your work email');

    app()->setLocale('pt_BR');
    expect($field->getHelperText())->toBe('Seu e-mail de trabalho');
});

it('keeps a null placeholder/helper text null', function (): void {
    $field = new StubField('email');

    expect($field->getPlaceholder())->toBeNull();
    expect($field->getHelperText())->toBeNull();
});

it('passes a plain literal placeholder through untranslated', function (): void {
    $field = (new StubField('email'))->placeholder('Type here');

    app()->setLocale('pt_BR');
    expect($field->getPlaceholder())->toBe('Type here');
});
