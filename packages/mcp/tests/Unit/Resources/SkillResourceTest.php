<?php

declare(strict_types=1);

use Arqel\Mcp\Resources\SkillResource;

it('lists resources with correct shape from closure-supplied packages', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => ['core', 'mcp'],
        contentReader: static fn (string $package): string => "stub for {$package}",
    );

    $entries = $resource->list();

    expect($entries)->toHaveCount(2)
        ->and($entries[0])->toBe([
            'uri' => 'arqel-skill://core',
            'name' => 'SKILL.md for arqel/core',
            'description' => 'AI agent context for the core package',
            'mimeType' => 'text/markdown',
        ])
        ->and($entries[1]['uri'])->toBe('arqel-skill://mcp');
});

it('returns an empty list when the resolver yields no packages', function (): void {
    $reader = static function (string $package): string {
        throw new RuntimeException('reader should not be called');
    };

    $resource = new SkillResource(
        packagesResolver: static fn (): array => [],
        contentReader: $reader,
    );

    expect($resource->list())->toBe([]);
});

it('reads contents wrapped in the MCP resource shape', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => ['core'],
        contentReader: static fn (string $package): string => "# SKILL.md for arqel/{$package}",
    );

    $payload = $resource->read('arqel-skill://core');

    expect($payload)->toBe([
        'contents' => [[
            'uri' => 'arqel-skill://core',
            'mimeType' => 'text/markdown',
            'text' => '# SKILL.md for arqel/core',
        ]],
    ]);
});

it('throws RuntimeException for an invalid URI scheme', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => [],
        contentReader: static fn (string $package): string => 'never',
    );

    $resource->read('http://example.com/SKILL.md');
})->throws(RuntimeException::class, 'Invalid URI: http://example.com/SKILL.md');

it('throws RuntimeException for a URI containing an invalid package name with uppercase', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => [],
        contentReader: static fn (string $package): string => 'never',
    );

    $resource->read('arqel-skill://Core');
})->throws(RuntimeException::class, 'Invalid URI: arqel-skill://Core');

it('throws RuntimeException for a URI containing slashes inside the package segment', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => [],
        contentReader: static fn (string $package): string => 'never',
    );

    $resource->read('arqel-skill://core/extra');
})->throws(RuntimeException::class, 'Invalid URI: arqel-skill://core/extra');

it('propagates a reader failure as a RuntimeException carrying the package name', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => ['mcp'],
        contentReader: static function (string $package): string {
            throw new RuntimeException("disk error for {$package}");
        },
    );

    $resource->read('arqel-skill://mcp');
})->throws(RuntimeException::class, 'SKILL.md not found for arqel/mcp');

it('does not call the reader when listing with an empty resolver', function (): void {
    $calls = 0;
    $resource = new SkillResource(
        packagesResolver: static fn (): array => [],
        contentReader: static function (string $package) use (&$calls): string {
            $calls++;

            return 'never';
        },
    );

    expect($resource->list())->toBe([])
        ->and($calls)->toBe(0);
});

it('produces a list entry per package when the resolver returns several names', function (): void {
    $resource = new SkillResource(
        packagesResolver: static fn (): array => ['core', 'fields', 'mcp', 'tenant'],
        contentReader: static fn (string $package): string => $package,
    );

    $uris = array_map(static fn (array $entry): string => $entry['uri'], $resource->list());

    expect($uris)->toBe([
        'arqel-skill://core',
        'arqel-skill://fields',
        'arqel-skill://mcp',
        'arqel-skill://tenant',
    ]);
});
