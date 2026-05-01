<?php

declare(strict_types=1);

use Arqel\Cli\Commands\InstallCommand;
use Arqel\Cli\Services\CompatibilityChecker;
use Arqel\Cli\Services\MarketplaceClient;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function arqelInstallTmpDir(string $suffix): string
{
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arqel-install-test-'.$suffix.'-'.bin2hex(random_bytes(4));
    mkdir($base, 0o755, true);

    return $base;
}

function makeStubFetcher(array $payload): Closure
{
    return fn (): string => json_encode($payload, JSON_THROW_ON_ERROR);
}

function runInstallCommand(array $input, array $payload, ?string $cwd = null, string $arqelVersion = '1.0.0'): array
{
    $cwd ??= arqelInstallTmpDir('basic');
    $previous = getcwd();
    chdir($cwd);

    try {
        $client = new MarketplaceClient('https://api.example.test/marketplace', makeStubFetcher($payload));
        $command = new InstallCommand($client, new CompatibilityChecker, $arqelVersion);

        $app = new Application;
        $app->addCommand($command);
        $tester = new CommandTester($app->find('install'));
        $tester->setInputs([]);
        $exit = $tester->execute($input, ['interactive' => false]);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$exit, $tester, $cwd];
}

it('happy path: generates install script using mocked client', function (): void {
    [$exit, $tester, $cwd] = runInstallCommand(
        input: [
            'package' => 'acme/arqel-stripe',
            '--no-prompts' => true,
            '--platform' => 'bash',
        ],
        payload: [
            'name' => 'stripe',
            'type' => 'fields',
            'composerPackage' => 'acme/arqel-stripe',
            'npmPackage' => '@acme/arqel-stripe',
            'compat' => ['arqel' => '^1.0'],
            'installerCommand' => 'stripe:install',
        ],
    );

    expect($exit)->toBe(0);
    $path = $cwd.'/arqel-install-acme-arqel-stripe.sh';
    expect(file_exists($path))->toBeTrue();
    $contents = (string) file_get_contents($path);
    expect($contents)
        ->toContain('composer require acme/arqel-stripe')
        ->toContain('npm install @acme/arqel-stripe')
        ->toContain('php artisan stripe:install');
    expect($tester->getDisplay())
        ->toContain('Found plugin stripe')
        ->toContain('Generated arqel-install-acme-arqel-stripe.sh')
        ->toContain('Compatibility OK');
});

it('rejects invalid package format with non-zero exit', function (): void {
    [$exit, $tester] = runInstallCommand(
        input: [
            'package' => 'not a package',
            '--no-prompts' => true,
            '--platform' => 'bash',
        ],
        payload: ['name' => 'x', 'type' => 'x', 'composerPackage' => 'a/b'],
    );

    expect($exit)->not->toBe(0);
    expect($tester->getDisplay())->toContain('Invalid package');
});

it('blocks install when compatibility check fails', function (): void {
    [$exit, $tester] = runInstallCommand(
        input: [
            'package' => 'acme/needs-v2',
            '--no-prompts' => true,
            '--platform' => 'bash',
        ],
        payload: [
            'name' => 'needs-v2',
            'type' => 'fields',
            'composerPackage' => 'acme/needs-v2',
            'compat' => ['arqel' => '^2.0'],
        ],
        arqelVersion: '1.5.0',
    );

    expect($exit)->not->toBe(0);
    expect($tester->getDisplay())
        ->toContain('requires Arqel ^2.0')
        ->toContain('Upgrade Arqel');
});

it('skips prompts and writes file in CWD when --no-prompts', function (): void {
    $cwd = arqelInstallTmpDir('no-prompts');

    [$exit] = runInstallCommand(
        input: [
            'package' => 'acme/minimal',
            '--no-prompts' => true,
            '--platform' => 'bash',
        ],
        payload: [
            'name' => 'minimal',
            'type' => 'plugin',
            'composerPackage' => 'acme/minimal',
        ],
        cwd: $cwd,
    );

    expect($exit)->toBe(0);
    expect(file_exists($cwd.'/arqel-install-acme-minimal.sh'))->toBeTrue();
});

it('emits powershell variant when --platform=powershell', function (): void {
    [$exit, $tester, $cwd] = runInstallCommand(
        input: [
            'package' => 'acme/winplugin',
            '--no-prompts' => true,
            '--platform' => 'powershell',
        ],
        payload: [
            'name' => 'winplugin',
            'type' => 'fields',
            'composerPackage' => 'acme/winplugin',
            'compat' => ['arqel' => '^1.0'],
        ],
    );

    expect($exit)->toBe(0);
    $path = $cwd.'/arqel-install-acme-winplugin.ps1';
    expect(file_exists($path))->toBeTrue();
    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('$ErrorActionPreference = "Stop"');
    expect($tester->getDisplay())->toContain('powershell -File arqel-install-acme-winplugin.ps1');
});

it('omits installer line when --no-installer is passed', function (): void {
    [$exit, , $cwd] = runInstallCommand(
        input: [
            'package' => 'acme/with-installer',
            '--no-prompts' => true,
            '--no-installer' => true,
            '--platform' => 'bash',
        ],
        payload: [
            'name' => 'with-installer',
            'type' => 'fields',
            'composerPackage' => 'acme/with-installer',
            'compat' => ['arqel' => '^1.0'],
            'installerCommand' => 'with-installer:install',
        ],
    );

    expect($exit)->toBe(0);
    $contents = (string) file_get_contents($cwd.'/arqel-install-acme-with-installer.sh');
    expect($contents)->not->toContain('php artisan with-installer:install');
});
