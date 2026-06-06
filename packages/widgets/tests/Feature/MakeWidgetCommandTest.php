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

it('scaffolds the data-bearing types by extending the matching base class', function (): void {
    $cases = [
        'stat' => 'StatWidget',
        'chart' => 'ChartWidget',
        'table' => 'TableWidget',
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

it('scaffolds a custom widget by composing CustomWidget::make (not subclassing the final base)', function (): void {
    $exit = runMakeWidget($this->tempBase, 'OnboardingProgress', 'custom');
    expect($exit)->toBe(0);

    $path = $this->tempBase.'/app/Widgets/OnboardingProgress.php';
    $contents = (string) file_get_contents($path);

    // CustomWidget is the documented final escape-hatch; the stub must compose
    // it via make(), never subclass it (which would fatal on autoload).
    expect($contents)->not->toContain('extends CustomWidget');
    expect($contents)->toContain('CustomWidget::make(');
    expect($contents)->toContain('public static function make()');
});

/**
 * Require the generated file in a fresh PHP subprocess (so a fatal
 * "cannot extend final class" surfaces as a non-zero exit instead of
 * killing the test runner) and instantiate / build the widget.
 *
 * Returns the subprocess exit code (0 = loaded + is a usable Widget).
 */
function requireAndInstantiate(string $generatedFile, string $fqcn, bool $compose): int
{
    $autoload = __DIR__.'/../../vendor/autoload.php';
    $build = $compose
        // Custom widgets are composed: the generated class exposes a static
        // make() returning a CustomWidget instance.
        ? sprintf('$w = \\%s::make();', $fqcn)
        // Data-bearing widgets are subclasses instantiated directly.
        : sprintf('$w = new \\%s();', $fqcn);

    $script = sprintf(
        'require %s; require %s; %s exit($w instanceof \\Arqel\\Widgets\\Widget ? 0 : 2);',
        var_export($autoload, true),
        var_export($generatedFile, true),
        $build,
    );

    $cmd = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>/dev/null';
    exec($cmd, $out, $code);

    return $code;
}

it('generates widgets that actually load and instantiate without fataling', function (): void {
    $cases = [
        // type => [class name, compose-via-make?]
        'stat' => ['StatExample', false],
        'chart' => ['ChartExample', false],
        'table' => ['TableExample', false],
        'custom' => ['CustomExample', true],
    ];

    foreach ($cases as $type => [$name, $compose]) {
        $exit = runMakeWidget($this->tempBase, $name, $type);
        expect($exit)->toBe(0);

        $path = $this->tempBase.'/app/Widgets/'.$name.'.php';
        $code = requireAndInstantiate($path, 'App\\Widgets\\'.$name, $compose);

        expect($code)->toBe(0, sprintf(
            'Generated %s widget [%s] failed to load/instantiate as a Widget (exit %d).',
            $type,
            $name,
            $code,
        ));
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
