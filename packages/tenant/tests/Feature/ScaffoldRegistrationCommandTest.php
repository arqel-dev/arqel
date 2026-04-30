<?php

declare(strict_types=1);

use Arqel\Tenant\Commands\ScaffoldRegistrationCommand;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Drive the command via its `setBasePath()` testing hook instead of
 * `$this->artisan(...)` so we can pin generated files to a temp dir
 * and never touch the host's `app/` or `resources/` folders.
 */
function runScaffoldCommand(string $basePath, bool $force = false): int
{
    $command = new ScaffoldRegistrationCommand;
    $command->setBasePath($basePath);
    $command->setLaravel(app());

    $input = new ArrayInput($force ? ['--force' => true] : []);
    $output = new BufferedOutput;

    return $command->run($input, $output);
}

beforeEach(function (): void {
    $this->tempBase = sys_get_temp_dir().'/arqel-tenant-scaffold-'.uniqid();
    mkdir($this->tempBase, 0o755, true);

    config()->set('arqel.tenancy.model', 'App\\Models\\Tenant');
});

afterEach(function (): void {
    $files = new Filesystem;
    if (is_dir($this->tempBase)) {
        $files->deleteDirectory($this->tempBase);
    }
});

it('writes the controller, routes snippet, and React stub on a clean run', function (): void {
    $exit = runScaffoldCommand($this->tempBase);

    expect($exit)->toBe(0);

    $controller = $this->tempBase.'/app/Http/Controllers/TenantRegistrationController.php';
    $routes = $this->tempBase.'/routes/tenant-registration.php';
    $page = $this->tempBase.'/resources/js/Pages/Arqel/TenantRegister.tsx';

    expect(file_exists($controller))->toBeTrue();
    expect(file_exists($routes))->toBeTrue();
    expect(file_exists($page))->toBeTrue();

    $controllerContents = (string) file_get_contents($controller);
    expect($controllerContents)->toContain('namespace App\\Http\\Controllers;');
    expect($controllerContents)->toContain('use App\\Models\\Tenant;');
    expect($controllerContents)->toContain('Tenant::create');
    expect($controllerContents)->toContain("Inertia::render('Arqel/TenantRegister')");
});

it('honours the configured tenancy model class when generating the controller', function (): void {
    config()->set('arqel.tenancy.model', 'Acme\\Domain\\Tenants\\Workspace');

    $exit = runScaffoldCommand($this->tempBase);

    expect($exit)->toBe(0);

    $contents = (string) file_get_contents(
        $this->tempBase.'/app/Http/Controllers/TenantRegistrationController.php',
    );

    expect($contents)->toContain('use Acme\\Domain\\Tenants\\Workspace;');
    expect($contents)->toContain('Workspace::create');
});

it('is idempotent — second run without --force leaves files untouched and exits 0', function (): void {
    runScaffoldCommand($this->tempBase);

    $controller = $this->tempBase.'/app/Http/Controllers/TenantRegistrationController.php';
    file_put_contents($controller, '// hand-edited');

    $exit = runScaffoldCommand($this->tempBase);

    expect($exit)->toBe(0);
    expect((string) file_get_contents($controller))->toBe('// hand-edited');
});

it('overwrites existing files when --force is passed', function (): void {
    runScaffoldCommand($this->tempBase);

    $controller = $this->tempBase.'/app/Http/Controllers/TenantRegistrationController.php';
    file_put_contents($controller, '// hand-edited');

    $exit = runScaffoldCommand($this->tempBase, force: true);

    expect($exit)->toBe(0);
    expect((string) file_get_contents($controller))->toContain('TenantRegistrationController');
});

it('appends the routes snippet to routes/web.php when present and skips on re-run', function (): void {
    mkdir($this->tempBase.'/routes', 0o755, true);
    file_put_contents($this->tempBase.'/routes/web.php', "<?php\n\n// existing routes\n");

    runScaffoldCommand($this->tempBase);

    $webRoutes = (string) file_get_contents($this->tempBase.'/routes/web.php');
    expect($webRoutes)->toContain('tenant.register.show');

    // Second run should not duplicate the snippet.
    runScaffoldCommand($this->tempBase);

    $afterSecond = (string) file_get_contents($this->tempBase.'/routes/web.php');
    expect(substr_count($afterSecond, 'tenant.register.show'))->toBe(1);
});
