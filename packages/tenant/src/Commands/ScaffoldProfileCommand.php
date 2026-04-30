<?php

declare(strict_types=1);

namespace Arqel\Tenant\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Scaffolds a tenant profile (settings) page.
 *
 * Generates three files in the host application:
 *   - `app/Http/Controllers/TenantSettingsController.php`
 *   - `routes/tenant-profile.php` (snippet — also appended to
 *     `routes/web.php` when present)
 *   - `resources/js/Pages/Arqel/TenantSettings.tsx`
 *
 * Resource integration (a dedicated `TenantSettingsResource`) is
 * intentionally deferred to a follow-up cross-package ticket once
 * the Resource API lands in Phase 1. This command stays scoped to a
 * standalone controller + route + Inertia stub.
 *
 * **Idempotence:** running the command twice without `--force`
 * leaves existing files untouched, reports them as skipped, and
 * exits with code 0 (matching `make:*` Laravel command semantics).
 *
 * **Testing:** the command exposes `setBasePath(string)` so the
 * test suite can point it at a temp directory without spinning up
 * a full testbench Laravel app — same hook used by
 * `ScaffoldRegistrationCommand`.
 */
final class ScaffoldProfileCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:tenant:scaffold-profile {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Scaffold tenant profile (settings) page: controller + route + React stub';

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

        $controllerTarget = $this->basePath('app/Http/Controllers/TenantSettingsController.php');
        $routesTarget = $this->basePath('routes/tenant-profile.php');
        $pageTarget = $this->basePath('resources/js/Pages/Arqel/TenantSettings.tsx');

        $controllerStub = $this->renderControllerStub($files, $tenantModel);
        $routesStub = (string) $files->get($this->stubPath('routes/tenant-profile.stub'));
        $pageStub = (string) $files->get($this->stubPath('pages/TenantSettings.tsx.stub'));

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

        $this->components->info('Tenant profile scaffold complete.');
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
     * `tenant.settings.edit` route. Idempotent.
     */
    private function maybeAppendToWebRoutes(Filesystem $files, bool $force): void
    {
        $webRoutes = $this->basePath('routes/web.php');

        if (! $files->exists($webRoutes)) {
            return;
        }

        $existing = (string) $files->get($webRoutes);

        if (str_contains($existing, 'tenant.settings.edit') && ! $force) {
            $this->components->info('routes/web.php already references tenant.settings.edit — left untouched.');

            return;
        }

        $snippet = (string) $files->get($this->stubPath('routes/tenant-profile.stub'));
        $files->append($webRoutes, "\n".$snippet);
        $this->components->info('Appended tenant profile routes to routes/web.php.');
    }

    private function renderControllerStub(Filesystem $files, string $tenantModel): string
    {
        $stub = (string) $files->get($this->stubPath('controllers/TenantSettingsController.stub'));

        return strtr($stub, [
            '{{namespace}}' => 'App\\Http\\Controllers',
            '{{modelClass}}' => ltrim($tenantModel, '\\'),
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
