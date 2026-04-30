<?php

declare(strict_types=1);

namespace Arqel\Mcp\Prompts;

use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * MCP prompt: `migrate_filament_resource`.
 *
 * Builds a structured user message asking the LLM to migrate a Filament
 * Resource PHP file to its Arqel equivalent. The Filament source file is
 * inlined into the prompt so the model has the exact source available.
 *
 * Path safety: the `filament_file` argument is resolved RELATIVE to the
 * project base path. Any value containing `..` is rejected outright to
 * prevent path traversal escapes — the file reader closure is NOT invoked
 * in that case.
 *
 * Closure injection (testability): construtor aceita `?Closure $fileReader`
 * com signature `(string $relativePath): string`. Default reader resolve
 * via `Container::getInstance()->make('path.base').'/'.$relativePath` e
 * usa `file_get_contents`. Mesmo padrão de `Resources\SkillResource`.
 */
final class MigrateFilamentResourcePrompt
{
    /** @var (Closure(string): string)|null */
    private ?Closure $fileReader;

    /**
     * @param (Closure(string): string)|null $fileReader Reads the file at the given project-relative path; throws on failure.
     */
    public function __construct(?Closure $fileReader = null)
    {
        $this->fileReader = $fileReader;
    }

    /**
     * @return array{name: string, description: string, arguments: array<int, array{name: string, description: string, required: bool}>}
     */
    public function schema(): array
    {
        return [
            'name' => 'migrate_filament_resource',
            'description' => 'Help migrate a Filament Resource to Arqel',
            'arguments' => [
                [
                    'name' => 'filament_file',
                    'description' => 'Path (relative to project root) to a Filament Resource PHP file',
                    'required' => true,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array{description: string, messages: array<int, array{role: string, content: array<int, array{type: string, text: string}>}>}
     */
    public function generate(array $args): array
    {
        $relativePath = $args['filament_file'] ?? null;

        if (! is_string($relativePath) || $relativePath === '') {
            throw new InvalidArgumentException("'filament_file' parameter is required and must be a non-empty string");
        }

        if (str_contains($relativePath, '..')) {
            throw new InvalidArgumentException("'filament_file' must not contain '..' path traversal segments");
        }

        $contents = $this->fileReader !== null
            ? ($this->fileReader)($relativePath)
            : $this->defaultRead($relativePath);

        $promptText = <<<TEXT
Migrate this Filament Resource to an Arqel Resource.

Source file (`{$relativePath}`):

```php
{$contents}
```

Migration guidelines:

- Arqel renders panels with **Inertia 3 + React 19**, not Livewire/Blade — no `\$this`, no Livewire actions
- `Filament\\Resources\\Resource` -> `Arqel\\Core\\Resources\\Resource`; keep `\$model`, `\$slug`, navigation properties
- Field types map 1:1 in many cases: `TextInput` -> `Field::text()`, `Select` -> `Field::select()`, `Toggle` -> `Field::boolean()`, `DateTimePicker` -> `Field::datetime()`, `RichEditor` -> `Field::richText()`
- Validation moves into a Laravel `FormRequest` (Arqel uses Laravel-native rules); avoid replicating Filament's chained `->required()->maxLength(...)` — express it once in the FormRequest
- `Tables\\Table::make()` -> `Table::make()` with `Column::text/badge/boolean/...`; eager-loading hints use `->with([...])` on the resource query, no `getEloquentQuery()` override
- Actions: Filament `Action::make()` is conceptually identical to `Arqel\\Core\\Actions\\Action::make()` — port name, label, form, and the closure body; `record` becomes the typed model
- Authorization stays in a Laravel **Policy** (no Filament-specific `can*` methods); Arqel auto-wires `viewAny/view/create/update/delete` from the Policy
- Produce the equivalent Arqel Resource class as a single PHP file, keeping namespace, imports, and visibility consistent with the Filament original
TEXT;

        return [
            'description' => 'Migration guidance: Filament Resource -> Arqel',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $promptText],
                    ],
                ],
            ],
        ];
    }

    private function defaultRead(string $relativePath): string
    {
        try {
            /** @var string $base */
            $base = Container::getInstance()->make('path.base');
        } catch (Throwable $e) {
            throw new RuntimeException("File not found: {$relativePath}", 0, $e);
        }

        $path = rtrim($base, '/').'/'.ltrim($relativePath, '/');
        $real = realpath($path);
        if ($real === false || ! is_file($real)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $contents = file_get_contents($real);
        if ($contents === false) {
            throw new RuntimeException("File not found: {$real}");
        }

        return $contents;
    }
}
