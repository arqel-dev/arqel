<?php

declare(strict_types=1);

namespace Arqel\Fields\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class MakeFieldCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:field
                            {name : The custom field name (e.g. RichMarkdown)}
                            {--force : Overwrite existing files without asking}';

    /** @var string */
    protected $description = 'Generate a custom Arqel Field (PHP class + React component stub)';

    public function handle(Filesystem $files): int
    {
        $rawName = $this->stringArg('name');
        $base = Str::studly(Str::beforeLast($rawName, 'Field') ?: $rawName);
        $className = $base.'Field';
        $componentName = $base.'Input';
        $type = Str::camel($base);

        $phpTarget = app_path('Arqel/Fields/'.$className.'.php');
        $tsxTarget = resource_path('js/Arqel/Fields/'.$componentName.'.tsx');

        $this->writeStub(
            $files,
            stub: $this->stubPath('field.stub'),
            target: $phpTarget,
            replacements: [
                '{{namespace}}' => 'App\\Arqel\\Fields',
                '{{class}}' => $className,
                '{{type}}' => $type,
                '{{component}}' => $componentName,
            ],
        );

        $this->writeStub(
            $files,
            stub: $this->stubPath('field-component.stub'),
            target: $tsxTarget,
            replacements: [
                '{{component}}' => $componentName,
                '{{type}}' => $type,
            ],
        );

        $this->printRegistrationHint($className, $type);

        return self::SUCCESS;
    }

    /**
     * @param array<string, string> $replacements
     */
    protected function writeStub(Filesystem $files, string $stub, string $target, array $replacements): void
    {
        if ($files->exists($target) && ! $this->shouldOverwrite($target)) {
            note("Skipped {$this->relative($target)} (already exists).");

            return;
        }

        $files->ensureDirectoryExists(dirname($target));

        $contents = strtr((string) $files->get($stub), $replacements);
        $files->put($target, $contents);

        info("Created {$this->relative($target)}.");
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

    protected function printRegistrationHint(string $className, string $type): void
    {
        note('Add the following line to your `App\\Providers\\ArqelServiceProvider::boot()`:');
        note('    \\Arqel\\Fields\\FieldFactory::register(\''.$type.'\', \\App\\Arqel\\Fields\\'.$className.'::class);');
        note('Then `FieldFactory::'.$type.'(\'name\')` becomes available in your Resources.');
    }

    protected function stubPath(string $name): string
    {
        return dirname(__DIR__, 2).'/stubs/'.$name;
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
