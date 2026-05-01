<?php

declare(strict_types=1);

use Arqel\Cli\Application;
use Arqel\Cli\Commands\NewCommand;
use Symfony\Component\Console\Tester\CommandTester;

function arqelTmpDir(string $suffix): string
{
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arqel-cli-test-'.$suffix.'-'.bin2hex(random_bytes(4));
    mkdir($base, 0o755, true);

    return $base;
}

function runNewCommand(array $input, ?string $cwd = null): array
{
    $cwd ??= arqelTmpDir('basic');
    $previous = getcwd();
    chdir($cwd);

    try {
        $tester = new CommandTester((new Application)->find('new'));
        $tester->setInputs([]);
        $exit = $tester->execute($input, ['interactive' => false]);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$exit, $tester, $cwd];
}

it('generates a bash script for a breeze + no-tenancy app', function (): void {
    [$exit, $tester, $cwd] = runNewCommand([
        'name' => 'my-app',
        '--no-prompts' => true,
        '--starter' => 'breeze',
        '--tenancy' => 'none',
        '--platform' => 'bash',
    ]);

    expect($exit)->toBe(0);
    $path = $cwd.'/arqel-setup-my-app.sh';
    expect(file_exists($path))->toBeTrue();
    $contents = (string) file_get_contents($path);
    expect($contents)
        ->toContain('laravel new my-app --breeze')
        ->toContain('composer require arqel/arqel')
        ->not->toContain('stancl/tenancy');
    expect($tester->getDisplay())->toContain('Generated arqel-setup-my-app.sh');
});

it('includes stancl/tenancy in the script when --tenancy=stancl', function (): void {
    [$exit, , $cwd] = runNewCommand([
        'name' => 'rental',
        '--no-prompts' => true,
        '--starter' => 'none',
        '--tenancy' => 'stancl',
        '--platform' => 'bash',
    ]);

    expect($exit)->toBe(0);
    $contents = (string) file_get_contents($cwd.'/arqel-setup-rental.sh');
    expect($contents)
        ->toContain('composer require stancl/tenancy')
        ->toContain('laravel new rental');
});

it('uses --jet flag when starter is jetstream', function (): void {
    [$exit, , $cwd] = runNewCommand([
        'name' => 'crm',
        '--no-prompts' => true,
        '--starter' => 'jetstream',
        '--tenancy' => 'none',
        '--platform' => 'bash',
    ]);

    expect($exit)->toBe(0);
    $contents = (string) file_get_contents($cwd.'/arqel-setup-crm.sh');
    expect($contents)->toContain('laravel new crm --jet');
});

it('rejects invalid project names with non-zero exit', function (): void {
    [$exit, $tester] = runNewCommand([
        'name' => '123-invalid',
        '--no-prompts' => true,
        '--platform' => 'bash',
    ]);

    expect($exit)->not->toBe(0);
    expect($tester->getDisplay())->toContain('Invalid project name');
});

it('writes a 0755 executable script on Unix platforms', function (): void {
    if (DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('chmod semantics not honoured on Windows.');
    }

    [$exit, , $cwd] = runNewCommand([
        'name' => 'execcheck',
        '--no-prompts' => true,
        '--platform' => 'bash',
    ]);

    expect($exit)->toBe(0);
    $path = $cwd.'/arqel-setup-execcheck.sh';
    $perms = fileperms($path) & 0o777;
    expect($perms & 0o111)->not->toBe(0);
});

it('produces a .ps1 file when platform=windows is forced', function (): void {
    [$exit, $tester, $cwd] = runNewCommand([
        'name' => 'winapp',
        '--no-prompts' => true,
        '--starter' => 'breeze',
        '--tenancy' => 'simple',
        '--platform' => 'powershell',
    ]);

    expect($exit)->toBe(0);
    $path = $cwd.'/arqel-setup-winapp.ps1';
    expect(file_exists($path))->toBeTrue();
    $contents = (string) file_get_contents($path);
    expect($contents)
        ->toContain('Set-Location winapp')
        ->toContain('composer require arqel/tenant');
    expect($tester->getDisplay())->toContain('powershell -File arqel-setup-winapp.ps1');
});

it('skips interactive prompts and warns when TTY does not support stty', function (): void {
    $cwd = arqelTmpDir('tty-fallback');
    $previous = getcwd();
    chdir($cwd);

    try {
        $tester = new CommandTester((new Application)->find('new'));
        $tester->setInputs([]);
        // interactive=true triggers the prompt branch; CommandTester runs without
        // a /dev/tty so ttySupportsPrompts() must short-circuit it.
        $exit = $tester->execute(['name' => 'tty-app'], ['interactive' => true]);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    expect($exit)->toBe(0);
    expect($tester->getDisplay())
        ->toContain('Non-POSIX TTY detected')
        ->toContain('Generated arqel-setup-tty-app.sh');
    expect(file_exists($cwd.'/arqel-setup-tty-app.sh'))->toBeTrue();
});

it('ttySupportsPrompts returns false in CI/test environments without /dev/tty', function (): void {
    expect(NewCommand::ttySupportsPrompts())->toBeFalse();
})->skipOnWindows();
