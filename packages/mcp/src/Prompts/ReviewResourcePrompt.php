<?php

declare(strict_types=1);

namespace Arqel\Mcp\Prompts;

use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * MCP prompt: `review_resource`.
 *
 * Builds a structured user message asking the LLM to review an existing
 * Arqel Resource PHP file for issues, code smells, and improvement
 * opportunities. The source is inlined verbatim into the prompt.
 *
 * Path safety: the `resource_file` argument must NOT contain `..`. The
 * file reader closure is not invoked when traversal is detected.
 */
final class ReviewResourcePrompt
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
            'name' => 'review_resource',
            'description' => 'Review an existing Arqel Resource for issues, code smells, and improvement opportunities',
            'arguments' => [
                [
                    'name' => 'resource_file',
                    'description' => 'Path (relative to project root) to an Arqel Resource PHP file',
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
        $relativePath = $args['resource_file'] ?? null;

        if (! is_string($relativePath) || $relativePath === '') {
            throw new InvalidArgumentException("'resource_file' parameter is required and must be a non-empty string");
        }

        if (str_contains($relativePath, '..')) {
            throw new InvalidArgumentException("'resource_file' must not contain '..' path traversal segments");
        }

        $contents = $this->fileReader !== null
            ? ($this->fileReader)($relativePath)
            : $this->defaultRead($relativePath);

        $promptText = <<<TEXT
Review this Arqel Resource and report issues, code smells, and improvement opportunities.

Source file (`{$relativePath}`):

```php
{$contents}
```

Review checklist (cite line numbers when possible):

- Missing fields the underlying model exposes that the Resource ignores
- Missing actions for common workflows (create/edit/delete + bulk variants where relevant)
- Missing or stale Policy wiring (every public Resource MUST have a Laravel Policy gate)
- N+1 query risks: relationships referenced in Table columns / Form selects without `->with([...])`
- Validation gaps: required/type/unique constraints absent in the FormRequest
- Missing relationships: foreign keys on the model that have no Field/Column counterpart
- Naming inconsistencies: `\$slug`, `\$navigationGroup`, `getLabel()`, `getPluralLabel()` should be coherent and PT-BR/EN consistent
- Anything else worth flagging: mass-assignment leaks, action authorization, soft-delete handling, tenancy scoping

Format the response as a Markdown checklist grouped by severity (`Blocker`, `Major`, `Minor`, `Nit`). End with a 2-3 sentence overall assessment.
TEXT;

        return [
            'description' => 'Arqel Resource review',
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
