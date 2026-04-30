<?php

declare(strict_types=1);

use Arqel\Mcp\Prompts\ReviewResourcePrompt;

it('exposes the canonical MCP schema shape', function (): void {
    $prompt = new ReviewResourcePrompt;

    $schema = $prompt->schema();

    expect($schema['name'])->toBe('review_resource')
        ->and($schema['description'])->toBe('Review an existing Arqel Resource for issues, code smells, and improvement opportunities')
        ->and($schema['arguments'])->toHaveCount(1)
        ->and($schema['arguments'][0]['name'])->toBe('resource_file')
        ->and($schema['arguments'][0]['required'])->toBeTrue()
        ->and($schema['arguments'][0]['description'])->toContain('Arqel Resource');
});

it('builds the review envelope inlining the file contents via the injected reader', function (): void {
    $fixture = "<?php\n\nclass PostResource extends Arqel\\Core\\Resources\\Resource {}\n";
    $captured = null;

    $prompt = new ReviewResourcePrompt(
        fileReader: function (string $path) use (&$captured, $fixture): string {
            $captured = $path;

            return $fixture;
        },
    );

    $payload = $prompt->generate(['resource_file' => 'app/Arqel/Resources/PostResource.php']);

    expect($captured)->toBe('app/Arqel/Resources/PostResource.php')
        ->and($payload['description'])->toBe('Arqel Resource review')
        ->and($payload['messages'][0]['role'])->toBe('user')
        ->and($payload['messages'][0]['content'][0]['type'])->toBe('text');

    $text = $payload['messages'][0]['content'][0]['text'];
    expect($text)->toContain('app/Arqel/Resources/PostResource.php')
        ->and($text)->toContain($fixture)
        ->and($text)->toContain('N+1')
        ->and($text)->toContain('Policy');
});

it('throws InvalidArgumentException when resource_file is missing', function (): void {
    $prompt = new ReviewResourcePrompt(
        fileReader: static fn (string $path): string => 'never',
    );

    $prompt->generate([]);
})->throws(InvalidArgumentException::class, "'resource_file' parameter is required");

it('throws InvalidArgumentException when resource_file is not a string', function (): void {
    $prompt = new ReviewResourcePrompt(
        fileReader: static fn (string $path): string => 'never',
    );

    $prompt->generate(['resource_file' => 123]);
})->throws(InvalidArgumentException::class, "'resource_file' parameter is required");

it('blocks path traversal and never invokes the file reader', function (): void {
    $invocations = 0;
    $prompt = new ReviewResourcePrompt(
        fileReader: static function (string $path) use (&$invocations): string {
            $invocations++;

            return 'never';
        },
    );

    try {
        $prompt->generate(['resource_file' => 'app/../../secrets.php']);
        expect()->fail('Expected InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('..')
            ->and($invocations)->toBe(0);
    }
});

it('propagates a RuntimeException from the file reader carrying the path', function (): void {
    $prompt = new ReviewResourcePrompt(
        fileReader: static function (string $path): string {
            throw new RuntimeException("File not found: {$path}");
        },
    );

    $prompt->generate(['resource_file' => 'gone.php']);
})->throws(RuntimeException::class, 'File not found: gone.php');
