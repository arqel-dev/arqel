<?php

declare(strict_types=1);

use Arqel\Widgets\Commands\MakeDashboardCommand;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function runMakeDashboard(string $basePath, string $name, ?string $id = null, bool $force = false): int
{
    $command = new MakeDashboardCommand;
    $command->setBasePath($basePath);
    $command->setLaravel(app());

    $args = ['name' => $name];
    if ($id !== null) {
        $args['--id'] = $id;
    }
    if ($force) {
        $args['--force'] = true;
    }

    return $command->run(new ArrayInput($args), new BufferedOutput);
}

beforeEach(function (): void {
    $this->tempBase = sys_get_temp_dir().'/arqel-dashboard-scaffold-'.uniqid();
    mkdir($this->tempBase, 0o755, true);
});

afterEach(function (): void {
    $files = new Filesystem;
    if (is_dir($this->tempBase)) {
        $files->deleteDirectory($this->tempBase);
    }
});

it('scaffolds a dashboard with id defaulted from the class name', function (): void {
    $exit = runMakeDashboard($this->tempBase, 'AnalyticsDashboard');

    expect($exit)->toBe(0);

    $path = $this->tempBase.'/app/Dashboards/AnalyticsDashboard.php';
    $contents = (string) file_get_contents($path);

    expect($contents)->toContain("Dashboard::make('analytics_dashboard'");
    expect($contents)->toContain("'Analytics Dashboard'");
});

it('honors an explicit --id', function (): void {
    runMakeDashboard($this->tempBase, 'Analytics2026', id: 'analytics-2026');

    $contents = (string) file_get_contents($this->tempBase.'/app/Dashboards/Analytics2026.php');
    expect($contents)->toContain("Dashboard::make('analytics-2026'");
});

it('is idempotent without --force and overwrites with it', function (): void {
    runMakeDashboard($this->tempBase, 'Repeated');
    $path = $this->tempBase.'/app/Dashboards/Repeated.php';
    file_put_contents($path, '// edited');

    $exit = runMakeDashboard($this->tempBase, 'Repeated');
    expect($exit)->toBe(0);
    expect((string) file_get_contents($path))->toBe('// edited');

    runMakeDashboard($this->tempBase, 'Repeated', force: true);
    expect((string) file_get_contents($path))->toContain('Dashboard::make');
});
