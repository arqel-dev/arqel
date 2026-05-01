<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiManager;
use Arqel\Ai\Mcp\Tools\SuggestFieldsTool;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

/**
 * Build a fresh ConfigurableFakeProvider + AiManager + tool tuple for a test.
 *
 * @return array{0: ConfigurableFakeProvider, 1: SuggestFieldsTool}
 */
function makeSuggestFieldsTool(string $textToReturn): array
{
    $provider = new ConfigurableFakeProvider('fake');
    $provider->textToReturn = $textToReturn;
    $manager = new AiManager(
        providers: ['fake' => $provider],
        costTracker: null,
        cache: app(AiCache::class),
    );

    return [$provider, new SuggestFieldsTool($manager)];
}

it('exposes a JSON Schema requiring model_name and model_fields', function (): void {
    [, $tool] = makeSuggestFieldsTool('noop');
    $schema = $tool->schema();

    expect($schema['name'])->toBe('suggest_fields');
    expect($schema['inputSchema']['required'])->toBe(['model_name', 'model_fields']);
});

it('returns AI suggestions for a column map', function (): void {
    [$provider, $tool] = makeSuggestFieldsTool(
        "- email: TextField (email column, simple string)\n- created_at: DateField (timestamp)"
    );

    $result = $tool([
        'model_name' => 'User',
        'model_fields' => [
            'email' => 'string',
            'created_at' => 'timestamp',
        ],
        'provider' => 'fake',
    ]);

    expect($result['model_name'])->toBe('User');
    expect($result['suggestions'])->toContain('TextField');
    expect($provider->lastPrompt)->toContain('email: string');
    expect($provider->lastPrompt)->toContain('created_at: timestamp');
});

it('rejects an empty model_fields map', function (): void {
    [, $tool] = makeSuggestFieldsTool('noop');

    expect(fn () => $tool([
        'model_name' => 'User',
        'model_fields' => [],
        'provider' => 'fake',
    ]))->toThrow(InvalidArgumentException::class, 'model_fields');
});
