<?php

declare(strict_types=1);

namespace Arqel\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class MakeResourceCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:resource
                            {model : The model class name (e.g. User or App\\\\Models\\\\User)}
                            {--with-policy : Also generate a Policy class via make:policy}
                            {--force : Overwrite an existing Resource without asking}';

    /** @var string */
    protected $description = 'Generate an Arqel Resource class';

    public function handle(Filesystem $files): int
    {
        $modelClass = $this->resolveModelClass($this->stringArg('model'));

        if (! class_exists($modelClass)) {
            error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $modelBasename = class_basename($modelClass);
        $resourceClass = $modelBasename.'Resource';
        $namespace = $this->resolveNamespace();
        $target = $this->resolveTargetPath($resourceClass);

        if ($files->exists($target) && ! $this->shouldOverwrite($target)) {
            note("Skipped {$this->relative($target)} (already exists).");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($target));

        $contents = strtr((string) $files->get($this->stubPath()), [
            '{{namespace}}' => $namespace,
            '{{class}}' => $resourceClass,
            '{{model}}' => $modelBasename,
            '{{modelClass}}' => ltrim($modelClass, '\\'),
        ]);

        $files->put($target, $contents);

        info("Created {$this->relative($target)}.");

        if ($this->option('with-policy')) {
            $this->callSilently('make:policy', [
                'name' => $modelBasename.'Policy',
                '--model' => $modelClass,
            ]);
            note("Generated app/Policies/{$modelBasename}Policy.php.");
        }

        return self::SUCCESS;
    }

    protected function resolveModelClass(string $model): string
    {
        if (str_contains($model, '\\')) {
            return ltrim($model, '\\');
        }

        return 'App\\Models\\'.Str::studly($model);
    }

    protected function resolveNamespace(): string
    {
        $configured = config('arqel.resources.namespace');

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '\\')
            : 'App\\Arqel\\Resources';
    }

    protected function resolveTargetPath(string $resourceClass): string
    {
        $configured = config('arqel.resources.path');

        $basePath = is_string($configured) && $configured !== ''
            ? $configured
            : app_path('Arqel/Resources');

        return $basePath.DIRECTORY_SEPARATOR.$resourceClass.'.php';
    }

    protected function shouldOverwrite(string $target): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm(
            label: "{$this->relative($target)} already exists. Overwrite?",
            default: false,
        );
    }

    protected function stubPath(): string
    {
        return dirname(__DIR__, 2).'/stubs/resource.stub';
    }

    protected function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    protected function stringArg(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
