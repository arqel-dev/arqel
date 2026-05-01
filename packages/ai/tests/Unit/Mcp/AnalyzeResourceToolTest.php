<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiManager;
use Arqel\Ai\Mcp\Tools\AnalyzeResourceTool;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Core\Resources\ResourceRegistry;

/**
 * Build a fresh ConfigurableFakeProvider + AiManager + tool tuple for a test.
 *
 * @return array{0: ConfigurableFakeProvider, 1: AnalyzeResourceTool}
 */
function makeAnalyzeResourceTool(string $textToReturn): array
{
    $provider = new ConfigurableFakeProvider('fake');
    $provider->textToReturn = $textToReturn;
    $manager = new AiManager(
        providers: ['fake' => $provider],
        costTracker: null,
        cache: app(AiCache::class),
    );

    return [$provider, new AnalyzeResourceTool($manager)];
}

it('exposes a JSON Schema requiring resource_slug', function (): void {
    [, $tool] = makeAnalyzeResourceTool('noop');
    $schema = $tool->schema();

    expect($schema['name'])->toBe('analyze_resource');
    expect($schema['inputSchema']['required'])->toBe(['resource_slug']);
});

it('returns an AI summary for a registered resource', function (): void {
    [$provider, $tool] = makeAnalyzeResourceTool(
        'Summary: this resource manages articles. Issues: none. Suggestions: add tests.',
    );

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);

    $result = $tool([
        'resource_slug' => 'ai-articles',
        'provider' => 'fake',
    ]);

    expect($result['resource_slug'])->toBe('ai-articles');
    expect($result['summary'])->toContain('Summary');
    expect($provider->completeCalls)->toBe(1);
    expect($provider->lastPrompt)->toContain('ai-articles');
    expect($provider->lastPrompt)->toContain('field_count');
});

it('throws when the slug is not registered', function (): void {
    [, $tool] = makeAnalyzeResourceTool('noop');

    expect(fn () => $tool([
        'resource_slug' => 'nonexistent',
        'provider' => 'fake',
    ]))->toThrow(InvalidArgumentException::class, 'nonexistent');
});

it('rejects an empty slug', function (): void {
    [, $tool] = makeAnalyzeResourceTool('noop');

    expect(fn () => $tool([
        'resource_slug' => '',
        'provider' => 'fake',
    ]))->toThrow(InvalidArgumentException::class, 'resource_slug');
});
