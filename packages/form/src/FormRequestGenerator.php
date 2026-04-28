<?php

declare(strict_types=1);

namespace Arqel\Form;

use RuntimeException;

/**
 * Generate `Illuminate\Foundation\Http\FormRequest` subclasses from
 * a Resource. The output mirrors the resource's Field schema —
 * `rules()` delegates to `FieldRulesExtractor` so changes in the
 * schema do not require regenerating the request.
 *
 * Two flavours are emitted per resource:
 *   - `Store{Model}Request` (ability: `create`)
 *   - `Update{Model}Request` (ability: `update`)
 *
 * Path convention: `app/Http/Requests/Arqel/`. The generator does
 * not assume Laravel's directory layout — pass the absolute
 * `targetPath` to `write()`.
 */
final class FormRequestGenerator
{
    public const string ACTION_STORE = 'store';

    public const string ACTION_UPDATE = 'update';

    private string $stubPath;

    public function __construct(?string $stubPath = null)
    {
        $this->stubPath = $stubPath ?? __DIR__.'/../stubs/form-request.stub';
    }

    /**
     * @param class-string $resourceClass
     */
    public function generate(string $resourceClass, string $action = self::ACTION_STORE, string $namespace = 'App\\Http\\Requests\\Arqel'): string
    {
        if (! class_exists($resourceClass)) {
            throw new RuntimeException("Resource class [{$resourceClass}] does not exist.");
        }

        $action = $action === self::ACTION_UPDATE ? self::ACTION_UPDATE : self::ACTION_STORE;

        $resourceShort = $this->classBasename($resourceClass);
        $modelShort = preg_replace('/Resource$/', '', $resourceShort) ?? $resourceShort;
        $className = ucfirst($action).$modelShort.'Request';

        $stub = file_get_contents($this->stubPath);
        if ($stub === false) {
            throw new RuntimeException("Stub file [{$this->stubPath}] could not be read.");
        }

        return strtr($stub, [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ resourceClass }}' => ltrim($resourceClass, '\\'),
            '{{ resourceClassShort }}' => $resourceShort,
            '{{ ability }}' => $action === self::ACTION_UPDATE ? 'update' : 'create',
        ]);
    }

    /**
     * Write both Store/Update requests for `$resourceClass` to
     * `$targetPath`. Returns the list of files actually written
     * (skipped files when `force=false` are excluded).
     *
     * @param class-string $resourceClass
     *
     * @return array<int, string>
     */
    public function write(string $resourceClass, string $targetPath, bool $force = false): array
    {
        $written = [];

        if (! is_dir($targetPath) && ! @mkdir($targetPath, 0o755, true) && ! is_dir($targetPath)) {
            throw new RuntimeException("Target path [{$targetPath}] is not writable.");
        }

        foreach ([self::ACTION_STORE, self::ACTION_UPDATE] as $action) {
            $modelShort = preg_replace('/Resource$/', '', $this->classBasename($resourceClass)) ?? '';
            $file = $targetPath.'/'.ucfirst($action).$modelShort.'Request.php';

            if (! $force && file_exists($file)) {
                continue;
            }

            $contents = $this->generate($resourceClass, $action);
            $bytes = file_put_contents($file, $contents);

            if ($bytes === false) {
                throw new RuntimeException("Could not write [{$file}].");
            }

            $written[] = $file;
        }

        return $written;
    }

    /**
     * @param class-string $class
     */
    private function classBasename(string $class): string
    {
        $segments = explode('\\', ltrim($class, '\\'));

        return end($segments) ?: $class;
    }
}
