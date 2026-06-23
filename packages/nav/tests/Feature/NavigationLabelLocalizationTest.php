<?php

declare(strict_types=1);

use Arqel\Nav\NavigationGroup;
use Arqel\Nav\NavigationItem;
use Illuminate\Support\Facades\Lang;

it('localizes a navigation group label that is a translation key', function (): void {
    Lang::addLines(['nav.people' => 'People'], 'en', 'app');
    Lang::addLines(['nav.people' => 'Pessoas'], 'pt_BR', 'app');

    $group = NavigationGroup::make('app::nav.people');

    app()->setLocale('en');
    expect($group->getLabel())->toBe('People')
        ->and($group->toArray()['label'])->toBe('People');

    app()->setLocale('pt_BR');
    expect($group->getLabel())->toBe('Pessoas')
        ->and($group->toArray()['label'])->toBe('Pessoas');
});

it('passes a plain literal navigation group label through untranslated', function (): void {
    $group = NavigationGroup::make('System');

    app()->setLocale('pt_BR');
    expect($group->getLabel())->toBe('System')
        ->and($group->toArray()['label'])->toBe('System');
});

it('localizes a navigation item label that is a translation key', function (): void {
    Lang::addLines(['nav.settings' => 'Settings'], 'en', 'app');
    Lang::addLines(['nav.settings' => 'Configurações'], 'pt_BR', 'app');

    $item = NavigationItem::make('app::nav.settings');

    app()->setLocale('en');
    expect($item->getLabel())->toBe('Settings')
        ->and($item->toArray()['label'])->toBe('Settings');

    app()->setLocale('pt_BR');
    expect($item->getLabel())->toBe('Configurações')
        ->and($item->toArray()['label'])->toBe('Configurações');
});

it('passes a plain literal navigation item label through untranslated', function (): void {
    $item = NavigationItem::make('Dashboard');

    app()->setLocale('pt_BR');
    expect($item->getLabel())->toBe('Dashboard')
        ->and($item->toArray()['label'])->toBe('Dashboard');
});
