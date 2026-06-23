<?php

declare(strict_types=1);

use Arqel\FieldsAdvanced\Blocks\Block;
use Arqel\FieldsAdvanced\Steps\Step;
use Arqel\FieldsAdvanced\Types\KeyValueField;
use Illuminate\Support\Facades\Lang;

it('localizes KeyValueField default column labels per request locale', function (): void {
    $field = KeyValueField::make('headers');

    app()->setLocale('en');
    $props = $field->getTypeSpecificProps();
    expect($props['keyLabel'])->toBe('Key')
        ->and($props['valueLabel'])->toBe('Value');

    app()->setLocale('pt_BR');
    $props = $field->getTypeSpecificProps();
    expect($props['keyLabel'])->toBe('Chave')
        ->and($props['valueLabel'])->toBe('Valor');
});

it('localizes an app-supplied KeyValueField label that is a translation key', function (): void {
    Lang::addLines(['fields.headers.key' => 'Header'], 'en', 'app');
    Lang::addLines(['fields.headers.key' => 'Cabeçalho'], 'pt_BR', 'app');

    $field = KeyValueField::make('headers')->keyLabel('app::fields.headers.key');

    app()->setLocale('en');
    expect($field->getTypeSpecificProps()['keyLabel'])->toBe('Header');

    app()->setLocale('pt_BR');
    expect($field->getTypeSpecificProps()['keyLabel'])->toBe('Cabeçalho');
});

it('passes a plain literal KeyValueField label through untranslated', function (): void {
    $field = KeyValueField::make('headers')->valueLabel('Raw value');

    app()->setLocale('pt_BR');
    expect($field->getTypeSpecificProps()['valueLabel'])->toBe('Raw value');
});

final class LocalizedFixtureBlock extends Block
{
    public static function type(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'app::blocks.hero';
    }

    public function schema(): array
    {
        return [];
    }
}

it('localizes a Block label that is a translation key at serialization time', function (): void {
    Lang::addLines(['blocks.hero' => 'Hero'], 'en', 'app');
    Lang::addLines(['blocks.hero' => 'Destaque'], 'pt_BR', 'app');

    $block = new LocalizedFixtureBlock;

    app()->setLocale('en');
    expect($block->toArray()['label'])->toBe('Hero');

    app()->setLocale('pt_BR');
    expect($block->toArray()['label'])->toBe('Destaque');
});

it('localizes an explicit Step label that is a translation key', function (): void {
    Lang::addLines(['wizard.profile' => 'Profile'], 'en', 'app');
    Lang::addLines(['wizard.profile' => 'Perfil'], 'pt_BR', 'app');

    $step = Step::make('profile')->label('app::wizard.profile');

    app()->setLocale('en');
    expect($step->toArray()['label'])->toBe('Profile');

    app()->setLocale('pt_BR');
    expect($step->toArray()['label'])->toBe('Perfil');
});

it('passes the humanized Step label fallback through untranslated', function (): void {
    $step = Step::make('user_details');

    app()->setLocale('pt_BR');
    expect($step->toArray()['label'])->toBe('User Details');
});
