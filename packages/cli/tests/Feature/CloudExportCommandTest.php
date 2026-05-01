<?php

declare(strict_types=1);

use Arqel\Cli\Commands\CloudExportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function arqelCloudTmpDir(string $suffix, bool $create = true): string
{
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arqel-cloud-'.$suffix.'-'.bin2hex(random_bytes(4));
    if ($create) {
        mkdir($base, 0o755, true);
    }

    return $base;
}

function arqelCloudCleanup(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }
    foreach ((array) scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..' || ! is_string($entry)) {
            continue;
        }
        arqelCloudCleanup($path.DIRECTORY_SEPARATOR.$entry);
    }
    @rmdir($path);
}

function runCloudExport(array $input): array
{
    $command = new CloudExportCommand;
    $app = new Application;
    $app->addCommand($command);
    $tester = new CommandTester($app->find('cloud:export'));
    $exit = $tester->execute($input, ['interactive' => false]);

    return [$exit, $tester];
}

it('happy path: exports template to empty target dir', function (): void {
    $target = arqelCloudTmpDir('happy');

    try {
        [$exit, $tester] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'demoapp',
        ]);

        expect($exit)->toBe(0);
        expect(file_exists($target.'/cloud.yml'))->toBeTrue();
        expect(file_exists($target.'/composer.json'))->toBeTrue();
        expect(file_exists($target.'/README.md'))->toBeTrue();
        expect(file_exists($target.'/app/Providers/ArqelServiceProvider.php'))->toBeTrue();
        expect($tester->getDisplay())->toContain('Exported');
    } finally {
        arqelCloudCleanup($target);
    }
});

it('refuses non-empty existing target dir', function (): void {
    $target = arqelCloudTmpDir('nonempty');
    file_put_contents($target.'/existing.txt', 'busy');

    try {
        [$exit, $tester] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'demoapp',
        ]);

        expect($exit)->not->toBe(0);
        expect($tester->getDisplay())->toContain('already exists and is not empty');
    } finally {
        arqelCloudCleanup($target);
    }
});

it('creates target dir when it does not exist', function (): void {
    $parent = arqelCloudTmpDir('absent-parent');
    $target = $parent.'/will-be-created';

    try {
        [$exit] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'fresh',
        ]);

        expect($exit)->toBe(0);
        expect(is_dir($target))->toBeTrue();
        expect(file_exists($target.'/cloud.yml'))->toBeTrue();
    } finally {
        arqelCloudCleanup($parent);
    }
});

it('replaces APP_NAME placeholder via --app-name', function (): void {
    $target = arqelCloudTmpDir('appname');

    try {
        [$exit] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'acmecorp',
        ]);

        expect($exit)->toBe(0);
        $cloud = (string) file_get_contents($target.'/cloud.yml');
        $composer = (string) file_get_contents($target.'/composer.json');
        expect($cloud)->toContain('name: acmecorp');
        expect($cloud)->not->toContain('{{APP_NAME}}');
        expect($composer)->toContain('your-org/acmecorp');
    } finally {
        arqelCloudCleanup($target);
    }
});

it('uses target-dir basename when --app-name is not provided', function (): void {
    $parent = arqelCloudTmpDir('basename-parent');
    $target = $parent.'/myapp';

    try {
        [$exit] = runCloudExport([
            'target-dir' => $target,
        ]);

        expect($exit)->toBe(0);
        $cloud = (string) file_get_contents($target.'/cloud.yml');
        expect($cloud)->toContain('name: myapp');
    } finally {
        arqelCloudCleanup($parent);
    }
});

it('output includes git init instructions', function (): void {
    $target = arqelCloudTmpDir('gitinstr');

    try {
        [$exit, $tester] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'demoapp',
        ]);

        expect($exit)->toBe(0);
        $display = $tester->getDisplay();
        expect($display)->toContain('git init');
        expect($display)->toContain('git add .');
        expect($display)->toContain('Initial Arqel app');
        expect($display)->toContain('Deploy to Laravel Cloud');
    } finally {
        arqelCloudCleanup($target);
    }
});

it('rejects invalid app-name with whitespace', function (): void {
    $target = arqelCloudTmpDir('invalid');

    try {
        [$exit, $tester] = runCloudExport([
            'target-dir' => $target,
            '--app-name' => 'bad name',
        ]);

        expect($exit)->not->toBe(0);
        expect($tester->getDisplay())->toContain('Invalid app-name');
    } finally {
        arqelCloudCleanup($target);
    }
});
