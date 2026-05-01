<?php

declare(strict_types=1);

use Arqel\Cli\Generators\InstallScriptGenerator;
use Arqel\Cli\Models\PluginMetadata;

function makePlugin(
    ?string $npm = null,
    ?string $installer = null,
): PluginMetadata {
    return new PluginMetadata(
        name: 'stripe-fields',
        type: 'fields',
        composerPackage: 'acme/arqel-stripe-fields',
        npmPackage: $npm,
        compat: ['arqel' => '^1.0'],
        installerCommand: $installer,
    );
}

it('renders a baseline bash script with composer require', function (): void {
    $script = (new InstallScriptGenerator(makePlugin()))->forBash();

    expect($script)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('composer require acme/arqel-stripe-fields')
        ->not->toContain('npm install')
        ->not->toContain('php artisan');
});

it('includes npm install when npm package present', function (): void {
    $script = (new InstallScriptGenerator(makePlugin(npm: '@acme/stripe-fields')))->forBash();

    expect($script)->toContain('npm install @acme/stripe-fields');
});

it('includes artisan installer when present and enabled', function (): void {
    $script = (new InstallScriptGenerator(
        makePlugin(installer: 'stripe-fields:install'),
        runArtisanInstaller: true,
    ))->forBash();

    expect($script)->toContain('php artisan stripe-fields:install');
});

it('omits artisan installer when runArtisanInstaller is false', function (): void {
    $script = (new InstallScriptGenerator(
        makePlugin(installer: 'stripe-fields:install'),
        runArtisanInstaller: false,
    ))->forBash();

    expect($script)->not->toContain('php artisan stripe-fields:install');
});

it('appends php artisan migrate when requested', function (): void {
    $script = (new InstallScriptGenerator(
        makePlugin(),
        runArtisanInstaller: false,
        runArtisanMigrate: true,
    ))->forBash();

    expect($script)->toContain('php artisan migrate');
});

it('renders a powershell variant with composer require and Write-Host', function (): void {
    $script = (new InstallScriptGenerator(
        makePlugin(npm: '@acme/stripe-fields', installer: 'stripe-fields:install'),
    ))->forPowershell();

    expect($script)
        ->toContain('$ErrorActionPreference = "Stop"')
        ->toContain('Write-Host')
        ->toContain('composer require acme/arqel-stripe-fields')
        ->toContain('npm install @acme/stripe-fields')
        ->toContain('php artisan stripe-fields:install');
});
