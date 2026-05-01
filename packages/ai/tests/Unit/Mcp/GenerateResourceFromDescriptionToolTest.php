<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiManager;
use Arqel\Ai\Mcp\Tools\GenerateResourceFromDescriptionTool;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

/**
 * Build a fresh ConfigurableFakeProvider + AiManager + tool tuple for a test.
 *
 * @return array{0: ConfigurableFakeProvider, 1: GenerateResourceFromDescriptionTool}
 */
function makeGenerateResourceTool(string $textToReturn): array
{
    $provider = new ConfigurableFakeProvider('fake');
    $provider->textToReturn = $textToReturn;
    $manager = new AiManager(
        providers: ['fake' => $provider],
        costTracker: null,
        cache: app(AiCache::class),
    );

    return [$provider, new GenerateResourceFromDescriptionTool($manager)];
}

it('exposes a JSON Schema with required fields', function (): void {
    [, $tool] = makeGenerateResourceTool('noop');
    $schema = $tool->schema();

    expect($schema['name'])->toBe('generate_resource_from_description');
    expect($schema['inputSchema']['required'])->toBe(['description', 'model_name']);
    expect($schema['inputSchema']['properties'])->toHaveKeys(['description', 'model_name', 'provider']);
});

it('returns generated PHP code and a suggested path on happy path', function (): void {
    [$provider, $tool] = makeGenerateResourceTool(
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Arqel\\Resources;\n\nfinal class BlogPostResource {}\n"
    );

    $result = $tool([
        'description' => 'A blog post resource with title, slug, body, and author relation.',
        'model_name' => 'BlogPost',
        'provider' => 'fake',
    ]);

    expect($result['resource_code'])->toContain('declare(strict_types=1)');
    expect($result['resource_code'])->toContain('BlogPostResource');
    expect($result['suggested_path'])->toBe('app/Arqel/Resources/BlogPostResource.php');
    expect($result['model_name'])->toBe('BlogPost');
    expect($provider->completeCalls)->toBe(1);
    expect($provider->lastPrompt)->toContain('A blog post resource');
    expect($provider->lastPrompt)->toContain('BlogPost');
});

it('rejects an empty description', function (): void {
    [, $tool] = makeGenerateResourceTool('noop');

    expect(fn () => $tool([
        'description' => '   ',
        'model_name' => 'Foo',
        'provider' => 'fake',
    ]))->toThrow(InvalidArgumentException::class, 'description');
});

it('rejects a non-PascalCase model name', function (): void {
    [, $tool] = makeGenerateResourceTool('noop');

    expect(fn () => $tool([
        'description' => 'Some entity',
        'model_name' => 'blog_post',
        'provider' => 'fake',
    ]))->toThrow(InvalidArgumentException::class, 'PascalCase');
});

it('embeds the model_name into the suggested path', function (): void {
    [, $tool] = makeGenerateResourceTool('<?php class Foo {}');

    $result = $tool([
        'description' => 'Customer entity',
        'model_name' => 'Customer',
        'provider' => 'fake',
    ]);

    expect($result['suggested_path'])->toContain('CustomerResource.php');
});
