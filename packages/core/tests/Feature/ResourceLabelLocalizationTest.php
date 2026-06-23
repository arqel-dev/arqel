<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\SingularOnlyLabelResource;
use Arqel\Core\Tests\Fixtures\Resources\TranslatableResource;
use Illuminate\Support\Facades\Lang;

it('localizes resource label, plural label and navigation group at serialization time', function (): void {
    Lang::addLines(['resources.member' => 'Member'], 'en', 'app');
    Lang::addLines(['resources.member' => 'Membro'], 'pt_BR', 'app');
    Lang::addLines(['resources.members' => 'Members'], 'en', 'app');
    Lang::addLines(['resources.members' => 'Membros'], 'pt_BR', 'app');
    Lang::addLines(['resources.group' => 'People'], 'en', 'app');
    Lang::addLines(['resources.group' => 'Pessoas'], 'pt_BR', 'app');

    app()->setLocale('en');
    expect(TranslatableResource::getLabel())->toBe('Member')
        ->and(TranslatableResource::getPluralLabel())->toBe('Members')
        ->and(TranslatableResource::getNavigationGroup())->toBe('People');

    app()->setLocale('pt_BR');
    expect(TranslatableResource::getLabel())->toBe('Membro')
        ->and(TranslatableResource::getPluralLabel())->toBe('Membros')
        ->and(TranslatableResource::getNavigationGroup())->toBe('Pessoas');
});

it('passes auto-derived literal resource labels through untranslated', function (): void {
    app()->setLocale('pt_BR');

    expect(PostResource::getLabel())->toBe('Post')
        ->and(PostResource::getPluralLabel())->toBe('Posts');
});

it('does not inflect a translated singular with the English pluralizer', function (): void {
    Lang::addLines(['resources.category' => 'Category'], 'en', 'app');
    Lang::addLines(['resources.category' => 'Categoria'], 'pt_BR', 'app');

    // Only an explicit singular $label is set (no $pluralLabel). The auto-plural
    // must not apply English morphology to the translated noun: a pt_BR
    // "Categoria" must never become "Categorias" via Str::plural().
    app()->setLocale('pt_BR');
    expect(SingularOnlyLabelResource::getLabel())->toBe('Categoria')
        ->and(SingularOnlyLabelResource::getPluralLabel())->toBe('Categoria');

    app()->setLocale('en');
    expect(SingularOnlyLabelResource::getLabel())->toBe('Category')
        ->and(SingularOnlyLabelResource::getPluralLabel())->toBe('Category');
});
