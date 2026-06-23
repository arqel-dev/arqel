<?php

declare(strict_types=1);

use Arqel\Form\Layout\Section;
use Arqel\Form\Layout\Tab;
use Illuminate\Support\Facades\Lang;

it('localizes a Tab label that is a translation key at serialization time', function (): void {
    Lang::addLines(['tabs.details' => 'Details'], 'en', 'app');
    Lang::addLines(['tabs.details' => 'Detalhes'], 'pt_BR', 'app');

    $tab = Tab::make('details', 'app::tabs.details');

    app()->setLocale('en');
    expect($tab->getTypeSpecificProps()['label'])->toBe('Details');

    app()->setLocale('pt_BR');
    expect($tab->getTypeSpecificProps()['label'])->toBe('Detalhes');
});

it('passes a plain literal Tab label through untranslated', function (): void {
    $tab = Tab::make('details', 'Details');

    app()->setLocale('pt_BR');
    expect($tab->getTypeSpecificProps()['label'])->toBe('Details');
});

it('localizes Section heading and description that are translation keys', function (): void {
    Lang::addLines(['section.profile' => 'Profile'], 'en', 'app');
    Lang::addLines(['section.profile' => 'Perfil'], 'pt_BR', 'app');
    Lang::addLines(['section.profile_desc' => 'Profile details'], 'en', 'app');
    Lang::addLines(['section.profile_desc' => 'Detalhes do perfil'], 'pt_BR', 'app');

    $section = Section::make('app::section.profile')->description('app::section.profile_desc');

    app()->setLocale('en');
    $props = $section->getTypeSpecificProps();
    expect($props['heading'])->toBe('Profile')
        ->and($props['description'])->toBe('Profile details');

    app()->setLocale('pt_BR');
    $props = $section->getTypeSpecificProps();
    expect($props['heading'])->toBe('Perfil')
        ->and($props['description'])->toBe('Detalhes do perfil');
});

it('passes a plain literal Section heading through untranslated', function (): void {
    $section = Section::make('Profile');

    app()->setLocale('pt_BR');
    expect($section->getTypeSpecificProps()['heading'])->toBe('Profile');
});

it('omits a null Section description without error', function (): void {
    $section = Section::make('Profile');

    app()->setLocale('pt_BR');
    expect($section->getTypeSpecificProps())->not->toHaveKey('description');
});
