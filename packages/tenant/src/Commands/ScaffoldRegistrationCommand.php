<?php

declare(strict_types=1);

namespace Arqel\Tenant\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Scaffolds a tenant registration onboarding flow.
 *
 * Generates three files in the host application:
 *   - `app/Http/Controllers/TenantRegistrationController.php`
 *   - `routes/tenant-registration.php` (snippet — also appended to
 *     `routes/web.php` when present)
 *   - `resources/js/Pages/Arqel/TenantRegister.tsx`
 *
 * The React stub is intentionally minimal — full UX is the
 * consumer's responsibility. Generated controller and routes are
 * a starting point, not a contract.
 *
 * **Idempotence:** running the command twice without `--force`
 * leaves existing files untouched, reports them as skipped, and
 * exits with code 0 (matching `make:*` Laravel command semantics).
 *
 * **Testing:** the command exposes `setBasePath(string)` so the
 * test suite can point it at a temp directory without spinning up
 * a full testbench Laravel app. This is the same pattern used by
 * Laravel core's stub publishers and keeps Pest tests fast.
 */
final class ScaffoldRegistrationCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:tenant:scaffold-registration {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Scaffold tenant registration controller + routes + React page stub';

    private ?string $basePathOverride = null;

    /**
     * Override the application base path used when resolving
     * generated file destinations. Test-only hook — production
     * callers should rely on Laravel's `base_path()`.
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePathOverride = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function handle(Filesystem $files): int
    {
        $force = (bool) $this->option('force');
        $tenantModel = $this->resolveTenantModelClass();

        $controllerTarget = $this->basePath('app/Http/Controllers/TenantRegistrationController.php');
        $routesTarget = $this->basePath('routes/tenant-registration.php');
        $pageTarget = $this->basePath('resources/js/Pages/Arqel/TenantRegister.tsx');

        $controllerStub = $this->renderControllerStub($files, $tenantModel);
        $routesStub = (string) $files->get($this->stubPath('routes/tenant-registration.stub'));
        $pageStub = (string) $files->get($this->stubPath('pages/TenantRegister.tsx.stub'));

        $writes = [
            ['target' => $controllerTarget, 'contents' => $controllerStub, 'label' => 'controller'],
            ['target' => $routesTarget, 'contents' => $routesStub, 'label' => 'routes snippet'],
            ['target' => $pageTarget, 'contents' => $pageStub, 'label' => 'React page stub'],
        ];

        foreach ($writes as $write) {
            if (! $this->writeFile($files, $write['target'], $write['contents'], $force, $write['label'])) {
                return self::FAILURE;
            }
        }

        $this->maybeAppendToWebRoutes($files, $force);

        $this->components->info('Tenant registration scaffold complete.');
        $this->components->info("Tenant model: {$tenantModel}");

        return self::SUCCESS;
    }

    private function writeFile(
        Filesystem $files,
        string $target,
        string $contents,
        bool $force,
        string $label,
    ): bool {
        $relative = $this->relative($target);

        if ($files->exists($target) && ! $force) {
            $this->components->info("Skipped {$relative} ({$label} already exists — re-run with --force to overwrite).");

            return true;
        }

        try {
            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, $contents);
        } catch (Throwable $e) {
            $this->components->error("Failed to write {$relative}: {$e->getMessage()}");

            return false;
        }

        $this->components->info("Wrote {$relative}.");

        return true;
    }

    /**
     * Append the route snippet to `routes/web.php` if that file
     * exists and does not already register the
     * `tenant.register.show` route. Idempotent.
     */
    private function maybeAppendToWebRoutes(Filesystem $files, bool $force): void
    {
        $webRoutes = $this->basePath('routes/web.php');

        if (! $files->exists($webRoutes)) {
            return;
        }

        $existing = (string) $files->get($webRoutes);

        if (str_contains($existing, 'tenant.register.show') && ! $force) {
            $this->components->info('routes/web.php already references tenant.register.show — left untouched.');

            return;
        }

        $snippet = (string) $files->get($this->stubPath('routes/tenant-registration.stub'));
        $files->append($webRoutes, "\n".$snippet);
        $this->components->info('Appended tenant registration routes to routes/web.php.');
    }

    private function renderControllerStub(Filesystem $files, string $tenantModel): string
    {
        $stub = (string) $files->get($this->stubPath('controllers/TenantRegistrationController.stub'));
        $modelBasename = $this->classBasename($tenantModel);

        return strtr($stub, [
            '{{namespace}}' => 'App\\Http\\Controllers',
            '{{modelClass}}' => ltrim($tenantModel, '\\'),
            '{{model}}' => $modelBasename,
        ]);
    }

    private function resolveTenantModelClass(): string
    {
        $configured = function_exists('config') ? config('arqel.tenancy.model') : null;

        if (is_string($configured) && $configured !== '') {
            return ltrim($configured, '\\');
        }

        return 'App\\Models\\Tenant';
    }

    private function classBasename(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    private function basePath(string $relative): string
    {
        $base = $this->basePathOverride ?? base_path();

        return $base.DIRECTORY_SEPARATOR.ltrim($relative, '/');
    }

    private function stubPath(string $relative): string
    {
        return dirname(__DIR__, 2).'/stubs/'.ltrim($relative, '/');
    }

    private function relative(string $path): string
    {
        $base = ($this->basePathOverride ?? base_path()).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
