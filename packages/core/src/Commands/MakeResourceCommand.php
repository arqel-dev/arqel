<?php

declare(strict_types=1);

namespace Arqel\Core\Commands;

use Arqel\Core\Generators\ResourceGenerator;
use Arqel\Core\Support\InteractiveTerminal;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

/**
 * `arqel:resource` — gera Resource classes em `app/Arqel/Resources`.
 *
 * Modos:
 * - **CLI direto:** `arqel:resource App\\Models\\Post --label=Post`.
 * - **Interactive (CLI-TUI-002):** sem args + TTY → wizard com Laravel
 *   Prompts (model picker, field detection via Schema, optional Policy
 *   / FormRequest / Pest test scaffolding).
 */
final class MakeResourceCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:resource
                            {model? : The model class name (e.g. User or App\\\\Models\\\\User)}
                            {--label= : Human-readable singular label}
                            {--group= : Navigation group}
                            {--icon= : Navigation icon (Lucide name)}
                            {--with-policy : Also generate a Policy class}
                            {--with-form-requests : Generate Store/Update FormRequest classes}
                            {--tests=none : Test framework: pest|none}
                            {--force : Overwrite existing files without asking}';

    /** @var string */
    protected $description = 'Generate an Arqel Resource class (interactive when no model is given)';

    public function handle(Filesystem $files): int
    {
        $modelArg = $this->stringArg('model');

        if ($modelArg === '' && $this->shouldRunInteractive()) {
            return $this->runInteractive($files);
        }

        if ($modelArg === '') {
            $reason = $this->input->isInteractive() && ! InteractiveTerminal::supportsPrompts()
                ? 'Interactive wizard unavailable in this terminal (non-POSIX TTY). Pass the model argument and use flags (--label, --group, --icon, --with-policy, --with-form-requests, --tests).'
                : 'Model argument is required when running non-interactively.';
            error($reason);

            return self::FAILURE;
        }

        return $this->runDirect($files, $modelArg);
    }

    private function shouldRunInteractive(): bool
    {
        if ($this->option('no-interaction') === true) {
            return false;
        }

        return $this->input->isInteractive() && InteractiveTerminal::supportsPrompts();
    }

    private function runDirect(Filesystem $files, string $modelArg): int
    {
        $modelClass = $this->resolveModelClass($modelArg);

        if (! class_exists($modelClass)) {
            error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $generator = new ResourceGenerator(
            modelClass: $modelClass,
            label: $this->stringOpt('label') !== '' ? $this->stringOpt('label') : Str::studly(class_basename($modelClass)),
            group: $this->nullableOpt('group'),
            icon: $this->nullableOpt('icon'),
            fields: [],
            withPolicy: (bool) $this->option('with-policy'),
            withFormRequests: (bool) $this->option('with-form-requests'),
            testFramework: $this->resolveTestFramework(),
            resourceNamespace: $this->resolveNamespace(),
        );

        return $this->writeArtifacts($files, $generator);
    }

    private function runInteractive(Filesystem $files): int
    {
        $modelClass = $this->promptForModel();

        if ($modelClass === null) {
            error('No model selected — aborting.');

            return self::FAILURE;
        }

        if (! class_exists($modelClass)) {
            error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $modelBasename = class_basename($modelClass);

        $label = text(
            label: 'Resource label?',
            default: Str::studly($modelBasename),
        );

        $group = text(label: 'Navigation group? (leave empty for none)', default: '');
        $icon = text(label: 'Navigation icon (Lucide)? (leave empty for none)', default: '');

        $fields = $this->inferFields($modelClass);

        if ($fields !== []) {
            table(
                headers: ['Column', 'Inferred field'],
                rows: array_map(
                    static fn (array $f): array => [(string) $f['name'], (string) $f['type']],
                    $fields,
                ),
            );
        } else {
            note('No columns detected (model table may not exist yet) — generating empty fields() body.');
        }

        $confirmed = confirm(label: 'Generate Resource?', default: true);
        if (! $confirmed) {
            note('Cancelled.');

            return self::SUCCESS;
        }

        $withPolicy = confirm(label: 'Generate Policy?', default: true);
        $withFormRequests = confirm(label: 'Generate Store/Update FormRequest classes?', default: false);
        $testFramework = select(
            label: 'Tests?',
            options: ['pest' => 'Pest feature test', 'none' => 'No tests'],
            default: 'pest',
        );

        $resolvedFramework = $testFramework === 'pest' ? 'pest' : null;

        $generator = new ResourceGenerator(
            modelClass: $modelClass,
            label: $label !== '' ? $label : Str::studly($modelBasename),
            group: $group !== '' ? $group : null,
            icon: $icon !== '' ? $icon : null,
            fields: $fields,
            withPolicy: $withPolicy,
            withFormRequests: $withFormRequests,
            testFramework: $resolvedFramework,
            resourceNamespace: $this->resolveNamespace(),
        );

        return $this->writeArtifacts($files, $generator);
    }

    private function writeArtifacts(Filesystem $files, ResourceGenerator $generator): int
    {
        $resourceTarget = $this->resolveTargetPath($generator->resourceClass());

        if ($files->exists($resourceTarget) && ! $this->option('force')) {
            note("Skipped {$this->relative($resourceTarget)} (already exists). Use --force to overwrite.");

            return self::SUCCESS;
        }

        $files->ensureDirectoryExists(dirname($resourceTarget));
        $files->put($resourceTarget, $generator->generateResourceFile());
        info("Created {$this->relative($resourceTarget)}.");

        if ($generator->withPolicy) {
            $policyTarget = app_path("Policies/{$generator->policyClass()}.php");
            if ($files->exists($policyTarget) && ! $this->option('force')) {
                note("Skipped {$this->relative($policyTarget)} (already exists).");
            } else {
                $files->ensureDirectoryExists(dirname($policyTarget));
                $files->put($policyTarget, $generator->generatePolicyFile());
                info("Created {$this->relative($policyTarget)}.");
            }
        }

        if ($generator->withFormRequests) {
            foreach (['store', 'update'] as $kind) {
                $prefix = $kind === 'store' ? 'Store' : 'Update';
                $target = app_path("Http/Requests/{$prefix}{$generator->modelBasename()}Request.php");
                if ($files->exists($target) && ! $this->option('force')) {
                    note("Skipped {$this->relative($target)} (already exists).");

                    continue;
                }
                $files->ensureDirectoryExists(dirname($target));
                $files->put($target, $generator->generateRequestFile($kind));
                info("Created {$this->relative($target)}.");
            }
        }

        if ($generator->testFramework === 'pest') {
            $testTarget = base_path("tests/Feature/Admin/{$generator->modelBasename()}ResourceTest.php");
            if ($files->exists($testTarget) && ! $this->option('force')) {
                note("Skipped {$this->relative($testTarget)} (already exists).");
            } else {
                $files->ensureDirectoryExists(dirname($testTarget));
                $files->put($testTarget, $generator->generateTestFile());
                info("Created {$this->relative($testTarget)}.");
            }
        }

        return self::SUCCESS;
    }

    private function promptForModel(): ?string
    {
        $candidates = $this->discoverModels();

        if ($candidates === []) {
            $value = text(label: 'Model class? (FQN, e.g. App\\Models\\Post)');

            return $value !== '' ? $value : null;
        }

        /** @var array<string, string> $options */
        $options = [];
        foreach ($candidates as $fqn) {
            $options[$fqn] = $fqn;
        }

        $picked = select(label: 'Model class?', options: $options);

        return is_string($picked) && $picked !== '' ? $picked : null;
    }

    /**
     * Discover Eloquent models under `app/Models` (recursive).
     *
     * @return list<class-string<Model>>
     */
    private function discoverModels(): array
    {
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return [];
        }

        $finder = (new Finder)->files()->in($modelsPath)->name('*.php');
        $found = [];
        foreach ($finder as $file) {
            $relative = str_replace([$modelsPath.DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR], ['', '', '\\'], $file->getRealPath() ?: $file->getPathname());
            $fqn = 'App\\Models\\'.$relative;
            if (class_exists($fqn) && is_subclass_of($fqn, Model::class)) {
                /** @var class-string<Model> $fqn */
                $found[] = $fqn;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Map DB columns to Arqel field types via `Schema::getColumnType()`.
     *
     * @param class-string $modelClass
     *
     * @return list<array{name: string, type: string}>
     */
    public function inferFields(string $modelClass): array
    {
        try {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();
            $columns = Schema::getColumnListing($table);
        } catch (Throwable) {
            return [];
        }

        $inferred = [];
        foreach ($columns as $column) {
            if (! is_string($column)) {
                continue;
            }
            $name = $column;
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'], true)) {
                continue;
            }

            try {
                $dbType = Schema::getColumnType($table, $name);
            } catch (Throwable) {
                $dbType = 'string';
            }

            $type = $this->mapColumnType($name, $dbType);
            $inferred[] = ['name' => $name, 'type' => $type];
        }

        return $inferred;
    }

    private function mapColumnType(string $column, string $dbType): string
    {
        if ($column === 'slug') {
            return 'slug';
        }

        if (str_ends_with($column, '_id')) {
            return 'belongsTo';
        }

        return match ($dbType) {
            'string', 'varchar', 'char' => 'text',
            'text', 'longtext', 'mediumtext' => 'textarea',
            'integer', 'bigint', 'smallint', 'int', 'tinyint' => 'number',
            'float', 'double', 'decimal' => 'number',
            'boolean', 'bool' => 'toggle',
            'date', 'datetime', 'timestamp' => 'dateTime',
            'json', 'jsonb' => 'keyValue',
            default => 'text',
        };
    }

    private function resolveModelClass(string $model): string
    {
        if (str_contains($model, '\\')) {
            return ltrim($model, '\\');
        }

        return 'App\\Models\\'.Str::studly($model);
    }

    private function resolveNamespace(): string
    {
        $configured = config('arqel.resources.namespace');

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '\\')
            : 'App\\Arqel\\Resources';
    }

    private function resolveTargetPath(string $resourceClass): string
    {
        $configured = config('arqel.resources.path');

        $basePath = is_string($configured) && $configured !== ''
            ? $configured
            : app_path('Arqel/Resources');

        return $basePath.DIRECTORY_SEPARATOR.$resourceClass.'.php';
    }

    private function resolveTestFramework(): ?string
    {
        $value = $this->stringOpt('tests');
        if ($value === '' || $value === 'none') {
            return null;
        }

        return $value === 'pest' ? 'pest' : null;
    }

    private function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function stringArg(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function stringOpt(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    private function nullableOpt(string $name): ?string
    {
        $value = $this->option($name);
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
