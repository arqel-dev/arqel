<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;
use Arqel\Ai\Tests\Fixtures\FakeAiImageResource;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Testing\TestResponse;

function authedUserForAnalyzeImage(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    return $user;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function postAnalyzeImage(TestCase $case, string $resourceSlug, string $field, array $payload): TestResponse
{
    return $case->actingAs(authedUserForAnalyzeImage())->postJson(
        "/admin/{$resourceSlug}/fields/{$field}/analyze-image",
        $payload,
    );
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with analyses and populateMapping when given an imageUrl', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiImageResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['A red apple.', 'fruit, red, healthy'];
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postAnalyzeImage($this, 'ai-photos', 'cover', [
        'imageUrl' => 'https://example.com/apple.jpg',
    ]);

    $response->assertOk();
    $body = (array) $response->json();
    expect($body)->toMatchArray([
        'analyses' => [
            'alt_text' => 'A red apple.',
            'tags' => 'fruit, red, healthy',
        ],
        'populateMapping' => [
            'alt_text' => 'cover_alt',
            'tags' => 'cover_tags',
        ],
    ]);
});

it('returns 200 when given an imageBase64 payload', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiImageResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['Alt text.', 'tag1, tag2'];
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postAnalyzeImage($this, 'ai-photos', 'cover', [
        'imageBase64' => 'data:image/png;base64,AAA',
    ]);

    $response->assertOk();
    expect($fake->optionsHistory[0]['image'] ?? null)->toBe('data:image/png;base64,AAA');
});

it('returns 422 when neither imageUrl nor imageBase64 is provided', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiImageResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postAnalyzeImage($this, 'ai-photos', 'cover', []);

    $response->assertStatus(422);
});

it('returns 404 when the resource slug is not registered', function (): void {
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postAnalyzeImage($this, 'non-existent', 'cover', [
        'imageUrl' => 'https://example.com/x.jpg',
    ]);

    $response->assertStatus(404);
});

it('returns 422 when the field is not an AiImageField on the resource', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postAnalyzeImage($this, 'ai-articles', 'summary', [
        'imageUrl' => 'https://example.com/x.jpg',
    ]);

    $response->assertStatus(422);
});
