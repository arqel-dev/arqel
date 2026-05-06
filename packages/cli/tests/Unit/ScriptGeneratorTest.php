<?php

declare(strict_types=1);

use Arqel\Cli\Generators\SetupScriptGenerator;

it('renders a bash script with the expected baseline commands', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'my-app',
        starter: 'react',
        tenancy: 'none',
    ))->forBash();

    expect($script)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('set -euo pipefail')
        ->toContain('laravel new my-app --react')
        ->toContain('cd my-app')
        ->toContain('composer require arqel-dev/framework')
        ->toContain('php artisan arqel:install')
        ->toContain('pnpm install')
        ->not->toContain('stancl/tenancy')
        ->not->toContain('arqel-dev/mcp');
});

it('adds stancl/tenancy when tenancy is stancl', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'rentals',
        starter: 'none',
        tenancy: 'stancl',
    ))->forBash();

    expect($script)
        ->toContain('composer require stancl/tenancy')
        ->toContain('laravel new rentals')
        ->not->toContain('--react')
        ->not->toContain('--vue');
});

it('legacy "jetstream" alias resolves to react and adds spatie multitenancy', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'crm',
        starter: 'jetstream',
        tenancy: 'spatie',
        firstResource: 'Customer',
        darkMode: false,
        mcpIntegration: true,
    ))->forBash();

    expect($script)
        ->toContain('laravel new crm --react')
        ->toContain('composer require spatie/laravel-multitenancy')
        ->toContain('php artisan arqel:resource Customer')
        ->toContain('composer require arqel-dev/mcp')
        ->toContain('php artisan arqel:mcp:install')
        ->not->toContain('--jet')
        ->not->toContain('Dark mode preset');
});

it('rejects monorepoPath when target does not contain packages/core/composer.json', function (): void {
    expect(fn () => new SetupScriptGenerator(appName: 'app', monorepoPath: '/tmp'))
        ->toThrow(InvalidArgumentException::class, 'Invalid monorepoPath');
});

it('emits path-repo wiring + dev-main requires when monorepoPath is set', function (): void {
    $monorepo = realpath(__DIR__.'/../../../..');
    expect($monorepo)->not->toBeFalse();
    expect(is_file($monorepo.'/packages/core/composer.json'))->toBeTrue();

    $script = (new SetupScriptGenerator(
        appName: 'local-app',
        starter: 'react',
        tenancy: 'simple',
        monorepoPath: $monorepo,
    ))->forBash();

    expect($script)
        ->toContain('composer config repositories.arqel')
        ->toContain('"type":"path"')
        ->toContain($monorepo.'/packages/*')
        ->toContain('composer config minimum-stability dev')
        ->toContain('arqel-dev/core:dev-main')
        ->toContain('arqel-dev/fields:dev-main')
        ->toContain('arqel-dev/tenant:dev-main') // simple tenancy -> arqel-dev/tenant gets the suffix
        ->not->toContain('composer require arqel-dev/framework');
});

it('legacy "breeze" alias resolves to react', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'legacy-app',
        starter: 'breeze',
    ))->forBash();

    expect($script)
        ->toContain('laravel new legacy-app --react')
        ->not->toContain('--breeze');
});

it('renders a PowerShell script with the expected commands', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'win-app',
        starter: 'react',
        tenancy: 'simple',
    ))->forPowershell();

    expect($script)
        ->toContain('$ErrorActionPreference = "Stop"')
        ->toContain('Set-Location win-app')
        ->toContain('Write-Host "==> Installing arqel-dev/framework"')
        ->toContain('composer require arqel-dev/tenant')
        ->toContain('laravel new win-app --react');
});

it('rejects invalid app names and unknown enums', function (): void {
    expect(fn () => new SetupScriptGenerator(appName: ''))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new SetupScriptGenerator(appName: '123-bad'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new SetupScriptGenerator(appName: 'ok', starter: 'sail'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new SetupScriptGenerator(appName: 'ok', tenancy: 'wild'))
        ->toThrow(InvalidArgumentException::class);
});

it('emits dark-mode hint only when enabled', function (): void {
    $on = (new SetupScriptGenerator(appName: 'dm', darkMode: true))->forBash();
    $off = (new SetupScriptGenerator(appName: 'dm', darkMode: false))->forBash();

    expect($on)->toContain('Dark mode preset enabled');
    expect($off)->not->toContain('Dark mode preset');
});
