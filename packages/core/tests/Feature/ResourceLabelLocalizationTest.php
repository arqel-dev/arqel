<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Resources\PostResource;
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
