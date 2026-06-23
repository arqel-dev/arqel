<?php

declare(strict_types=1);

it('resolves the en messages namespace', function (): void {
    app()->setLocale('en');

    expect(__('arqel::messages.actions.create'))->toBe('Create')
        ->and(__('arqel::messages.actions.cancel'))->toBe('Cancel')
        ->and(__('arqel::messages.confirmation.delete'))->toBe('Are you sure you want to delete this?');
});

it('resolves the pt_BR messages namespace', function (): void {
    app()->setLocale('pt_BR');

    expect(__('arqel::messages.actions.create'))->toBe('Criar')
        ->and(__('arqel::messages.actions.cancel'))->toBe('Cancelar')
        ->and(__('arqel::messages.confirmation.delete'))->toBe('Tem certeza que deseja excluir?');
});

it('exposes table, form and actions namespaces in both locales', function (): void {
    app()->setLocale('en');

    expect(__('arqel::table.empty'))->toBe('No records found.')
        ->and(__('arqel::form.submit'))->toBe('Submit')
        ->and(__('arqel::actions.delete'))->toBe('Delete');

    app()->setLocale('pt_BR');

    expect(__('arqel::table.empty'))->toBe('Nenhum registro encontrado.')
        ->and(__('arqel::form.submit'))->toBe('Enviar')
        ->and(__('arqel::actions.delete'))->toBe('Excluir');
});

it('falls back to en when the locale is missing', function (): void {
    app()->setLocale('xx_YY');
    app()->setFallbackLocale('en');

    expect(__('arqel::messages.actions.create'))->toBe('Create');
});

it('substitutes placeholders in pagination strings', function (): void {
    app()->setLocale('en');

    expect(__('arqel::table.pagination.showing', ['from' => 1, 'to' => 10, 'total' => 42]))
        ->toBe('Showing 1 to 10 of 42 results');
});

it('exposes the theme-toggle aria/title copy in both locales', function (): void {
    // Keys back @arqel-dev/theme's <ThemeToggle> aria-label/title; the English
    // values are the React fallbacks so the accessible name stays stable when
    // no translation prop is present.
    app()->setLocale('en');

    expect(__('arqel::arqel.theme.toggle.system'))->toBe('Theme: system (click for light)')
        ->and(__('arqel::arqel.theme.toggle.light'))->toBe('Theme: light (click for dark)')
        ->and(__('arqel::arqel.theme.toggle.dark'))->toBe('Theme: dark (click for system)');

    app()->setLocale('pt_BR');

    expect(__('arqel::arqel.theme.toggle.system'))->toBe('Tema: sistema (clique para claro)')
        ->and(__('arqel::arqel.theme.toggle.light'))->toBe('Tema: claro (clique para escuro)')
        ->and(__('arqel::arqel.theme.toggle.dark'))->toBe('Tema: escuro (clique para sistema)');
});

it('exposes the skip-link default label in both locales', function (): void {
    app()->setLocale('en');
    expect(__('arqel::arqel.a11y.skip_to_content'))->toBe('Skip to main content');

    app()->setLocale('pt_BR');
    expect(__('arqel::arqel.a11y.skip_to_content'))->toBe('Pular para o conteúdo principal');
});

it('exposes the image/file/password field labels in both locales', function (): void {
    app()->setLocale('en');

    expect(__('arqel::arqel.fields.image.preview_alt'))->toBe('Preview')
        ->and(__('arqel::arqel.fields.image.choose'))->toBe('Choose image')
        ->and(__('arqel::arqel.fields.image.replace'))->toBe('Replace image')
        ->and(__('arqel::arqel.fields.file.browse'))->toBe('Browse')
        ->and(__('arqel::arqel.fields.file.choose_another'))->toBe('Choose another file')
        ->and(__('arqel::arqel.fields.password.show'))->toBe('Show password')
        ->and(__('arqel::arqel.fields.password.hide'))->toBe('Hide password');

    app()->setLocale('pt_BR');

    expect(__('arqel::arqel.fields.image.preview_alt'))->toBe('Pré-visualização')
        ->and(__('arqel::arqel.fields.image.choose'))->toBe('Escolher imagem')
        ->and(__('arqel::arqel.fields.image.replace'))->toBe('Substituir imagem')
        ->and(__('arqel::arqel.fields.file.browse'))->toBe('Procurar')
        ->and(__('arqel::arqel.fields.file.choose_another'))->toBe('Escolher outro arquivo')
        ->and(__('arqel::arqel.fields.password.show'))->toBe('Mostrar senha')
        ->and(__('arqel::arqel.fields.password.hide'))->toBe('Ocultar senha');
});

it('exposes the realtime connection-banner copy in both locales', function (): void {
    // These keys back @arqel-dev/realtime's <ConnectionStatusBanner>; the
    // English values must match the React fallbacks so the accessible name is
    // stable when no translation prop is present.
    app()->setLocale('en');

    expect(__('arqel::arqel.realtime.connecting'))->toBe('Connecting...')
        ->and(__('arqel::arqel.realtime.disconnected'))->toBe('Connection lost. Reconnecting...')
        ->and(__('arqel::arqel.realtime.failed'))->toBe('Connection failed. Refresh page.');

    app()->setLocale('pt_BR');

    expect(__('arqel::arqel.realtime.connecting'))->toBe('Conectando...')
        ->and(__('arqel::arqel.realtime.disconnected'))->toBe('Conexão perdida. Reconectando...')
        ->and(__('arqel::arqel.realtime.failed'))->toBe('Falha na conexão. Atualize a página.');
});
