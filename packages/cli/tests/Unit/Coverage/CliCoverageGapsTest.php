<?php

declare(strict_types=1);

use Arqel\Cli\Application;
use Arqel\Cli\Commands\NewCommand;
use Arqel\Cli\Generators\SetupScriptGenerator;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * CLI-TUI-005 — Coverage gaps complementando ScriptGeneratorTest e
 * NewCommandTest. Foca em ramos que B41 não tocou (validação especial,
 * tenancy=spatie em forBash, mcpIntegration em forBash, sanity check da
 * sintaxe PowerShell, registro de comandos e --no-prompts sem first-resource).
 */

it('rejects app names with whitespace, dots, slashes or extended ASCII', function (string $bad): void {
    expect(fn () => new SetupScriptGenerator(appName: $bad))
        ->toThrow(InvalidArgumentException::class, "Invalid app name '{$bad}'");
})->with([
    'has space' => ['my app'],
    'has dot' => ['my.app'],
    'has slash' => ['acme/admin'],
    'extended ascii' => ['café'],
    'starts with dash' => ['-leading'],
    'starts with digit' => ['1bad'],
]);

it('emits spatie/laravel-multitenancy in forBash() when tenancy is spatie', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'multi',
        starter: 'react',
        tenancy: 'spatie',
    ))->forBash();

    expect($script)
        ->toContain('composer require spatie/laravel-multitenancy')
        ->toContain('laravel new multi --react')
        ->not->toContain('stancl/tenancy')
        ->not->toContain('arqel-dev/tenant');
});

it('appends composer require arqel-dev/mcp in forBash() when mcpIntegration is true', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'mcp-app',
        mcpIntegration: true,
    ))->forBash();

    $mcpRequirePosition = strpos($script, 'composer require arqel-dev/mcp');
    $arqelInstallPosition = strpos($script, 'php artisan arqel:install');

    expect($mcpRequirePosition)->not->toBeFalse()
        ->and($arqelInstallPosition)->not->toBeFalse()
        ->and($script)->toContain('php artisan arqel:mcp:install');

    // mcp deve vir depois de arqel:install (ordem importa para o usuário).
    expect((int) $mcpRequirePosition > (int) $arqelInstallPosition)->toBeTrue();
});

it('forPowershell() output is syntactically plausible', function (): void {
    $script = (new SetupScriptGenerator(
        appName: 'win-sanity',
        starter: 'breeze',
        tenancy: 'stancl',
        firstResource: 'Order',
        mcpIntegration: true,
    ))->forPowershell();

    expect($script)
        ->toStartWith('# Arqel setup script')
        ->toContain('$ErrorActionPreference = "Stop"')
        ->toContain('Set-Location win-sanity')
        // Deve conter Write-Host (não echo de bash).
        ->toContain('Write-Host')
        ->not->toContain('set -euo pipefail')
        ->not->toContain('#!/usr/bin/env bash');

    // Heurística: backticks em PowerShell são line-continuation; não deve
    // haver backticks soltos no script gerado.
    expect(substr_count($script, '`'))->toBe(0);

    // Heurística: cada Write-Host deve fechar todas aspas duplas que abre.
    foreach (preg_split('/\R/', $script) ?: [] as $line) {
        if (str_starts_with($line, 'Write-Host')) {
            expect(substr_count($line, '"') % 2)->toBe(0);
        }
    }
});

it('Application registers the `new` command (regression)', function (): void {
    $app = new Application;

    expect($app->has('new'))->toBeTrue();

    $command = $app->find('new');
    expect($command)->toBeInstanceOf(NewCommand::class);

    // Ao menos um comando útil registrado (sanity contra regressão de
    // construtor que limpe o registry sem querer).
    $names = array_keys($app->all());
    $userCommands = array_filter($names, static fn (string $n): bool => ! str_starts_with($n, '_') && ! in_array($n, ['help', 'list', 'completion'], true));
    expect(count($userCommands))->toBeGreaterThanOrEqual(1);
});

it('--no-prompts without --first-resource leaves the script free of arqel:resource', function (): void {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arqel-cli-noprompts-'.bin2hex(random_bytes(4));
    mkdir($base, 0o755, true);

    $previous = getcwd();
    chdir($base);
    try {
        $tester = new CommandTester((new Application)->find('new'));
        $exit = $tester->execute([
            'name' => 'noprompt',
            '--no-prompts' => true,
            '--starter' => 'none',
            '--tenancy' => 'none',
            '--platform' => 'bash',
        ], ['interactive' => false]);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    expect($exit)->toBe(0);

    $contents = (string) file_get_contents($base.'/arqel-setup-noprompt.sh');
    expect($contents)
        ->toContain('php artisan arqel:install')
        ->not->toContain('php artisan arqel:resource');
});
