<?php

declare(strict_types=1);

use Arqel\Mcp\Prompts\MigrateFilamentResourcePrompt;

it('exposes the canonical MCP schema shape', function (): void {
    $prompt = new MigrateFilamentResourcePrompt;

    $schema = $prompt->schema();

    expect($schema['name'])->toBe('migrate_filament_resource')
        ->and($schema['description'])->toBe('Help migrate a Filament Resource to Arqel')
        ->and($schema['arguments'])->toHaveCount(1)
        ->and($schema['arguments'][0]['name'])->toBe('filament_file')
        ->and($schema['arguments'][0]['required'])->toBeTrue()
        ->and($schema['arguments'][0]['description'])->toContain('Filament Resource');
});

it('builds the migration envelope inlining the file contents via the injected reader', function (): void {
    $fixture = "<?php\n\nclass UserResource extends Filament\\Resources\\Resource {}\n";
    $captured = null;

    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: function (string $path) use (&$captured, $fixture): string {
            $captured = $path;

            return $fixture;
        },
    );

    $payload = $prompt->generate(['filament_file' => 'app/Filament/Resources/UserResource.php']);

    expect($captured)->toBe('app/Filament/Resources/UserResource.php')
        ->and($payload['description'])->toBe('Migration guidance: Filament Resource -> Arqel')
        ->and($payload['messages'])->toHaveCount(1)
        ->and($payload['messages'][0]['role'])->toBe('user')
        ->and($payload['messages'][0]['content'][0]['type'])->toBe('text');

    $text = $payload['messages'][0]['content'][0]['text'];
    expect($text)->toContain('app/Filament/Resources/UserResource.php')
        ->and($text)->toContain($fixture)
        ->and($text)->toContain('```php')
        ->and($text)->toContain('Inertia 3 + React 19');
});

it('throws InvalidArgumentException when filament_file is missing', function (): void {
    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: static fn (string $path): string => 'never',
    );

    $prompt->generate([]);
})->throws(InvalidArgumentException::class, "'filament_file' parameter is required");

it('throws InvalidArgumentException when filament_file is not a string', function (): void {
    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: static fn (string $path): string => 'never',
    );

    $prompt->generate(['filament_file' => ['nope']]);
})->throws(InvalidArgumentException::class, "'filament_file' parameter is required");

it('throws InvalidArgumentException when filament_file is an empty string', function (): void {
    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: static fn (string $path): string => 'never',
    );

    $prompt->generate(['filament_file' => '']);
})->throws(InvalidArgumentException::class, "'filament_file' parameter is required");

it('blocks path traversal and never invokes the file reader', function (): void {
    $invocations = 0;
    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: static function (string $path) use (&$invocations): string {
            $invocations++;

            return 'never';
        },
    );

    try {
        $prompt->generate(['filament_file' => '../../etc/passwd']);
        expect()->fail('Expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('..')
            ->and($invocations)->toBe(0);
    }
});

it('propagates a RuntimeException from the file reader carrying the path', function (): void {
    $prompt = new MigrateFilamentResourcePrompt(
        fileReader: static function (string $path): string {
            throw new RuntimeException("File not found: {$path}");
        },
    );

    $prompt->generate(['filament_file' => 'missing.php']);
})->throws(RuntimeException::class, 'File not found: missing.php');
