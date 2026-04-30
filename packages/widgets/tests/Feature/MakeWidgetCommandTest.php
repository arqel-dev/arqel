<?php

declare(strict_types=1);

use Arqel\Widgets\Commands\MakeWidgetCommand;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Drive the command via `setBasePath()` so generated files land in a
 * temp dir and never touch the host's `app/` folder.
 */
function runMakeWidget(string $basePath, string $name, string $type = 'stat', bool $force = false): int
{
    $command = new MakeWidgetCommand;
    $command->setBasePath($basePath);
    $command->setLaravel(app());

    $args = ['name' => $name, '--type' => $type];
    if ($force) {
        $args['--force'] = true;
    }

    return $command->run(new ArrayInput($args), new BufferedOutput);
}

beforeEach(function (): void {
    $this->tempBase = sys_get_temp_dir().'/arqel-widget-scaffold-'.uniqid();
    mkdir($this->tempBase, 0o755, true);
});

afterEach(function (): void {
    $files = new Filesystem;
    if (is_dir($this->tempBase)) {
        $files->deleteDirectory($this->tempBase);
    }
});

it('scaffolds a stat widget by default', function (): void {
    $exit = runMakeWidget($this->tempBase, 'TotalUsers', 'stat');

    expect($exit)->toBe(0);

    $path = $this->tempBase.'/app/Widgets/TotalUsers.php';
    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('class TotalUsers extends StatWidget');
    expect($contents)->toContain("parent::__construct('total_users')");
});

it('scaffolds each of the 4 widget types with the right base class', function (): void {
    $cases = [
        'stat' => 'StatWidget',
        'chart' => 'ChartWidget',
        'table' => 'TableWidget',
        'custom' => 'CustomWidget',
    ];

    foreach ($cases as $type => $base) {
        $name = ucfirst($type).'WidgetCase';
        $exit = runMakeWidget($this->tempBase, $name, $type);
        expect($exit)->toBe(0);

        $path = $this->tempBase.'/app/Widgets/'.$name.'.php';
        $contents = (string) file_get_contents($path);
        expect($contents)->toContain('extends '.$base);
    }
});

it('rejects an unknown widget type', function (): void {
    $exit = runMakeWidget($this->tempBase, 'Whatever', 'bogus');

    expect($exit)->toBe(1);
    expect(file_exists($this->tempBase.'/app/Widgets/Whatever.php'))->toBeFalse();
});

it('is idempotent: a second run without --force leaves the file untouched', function (): void {
    runMakeWidget($this->tempBase, 'Repeated', 'stat');
    $path = $this->tempBase.'/app/Widgets/Repeated.php';
    file_put_contents($path, '// edited by user');

    $exit = runMakeWidget($this->tempBase, 'Repeated', 'stat');

    expect($exit)->toBe(0);
    expect((string) file_get_contents($path))->toBe('// edited by user');
});

it('overwrites existing files with --force', function (): void {
    runMakeWidget($this->tempBase, 'Overwritten', 'stat');
    $path = $this->tempBase.'/app/Widgets/Overwritten.php';
    file_put_contents($path, '// edited');

    $exit = runMakeWidget($this->tempBase, 'Overwritten', 'stat', force: true);

    expect($exit)->toBe(0);
    expect((string) file_get_contents($path))->toContain('extends StatWidget');
});
