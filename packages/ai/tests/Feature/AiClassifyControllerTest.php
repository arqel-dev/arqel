<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\Fixtures\FakeAiSelectResource;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Testing\TestResponse;

function authedUserForClassify(): AuthUser
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
function postClassify(TestCase $case, string $resourceSlug, string $field, array $payload): TestResponse
{
    return $case->actingAs(authedUserForClassify())->postJson(
        "/admin/{$resourceSlug}/fields/{$field}/classify",
        $payload,
    );
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with key and label on the happy path', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiSelectResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'tech';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postClassify($this, 'ai-posts', 'category', [
        'formData' => ['title' => 'New CPU', 'description' => 'Apple M5 launched'],
    ]);

    $response->assertOk();
    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    expect($body)->toBe(['key' => 'tech', 'label' => 'Technology']);
});

it('returns 404 when the resource slug is not registered', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'tech';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postClassify($this, 'non-existent', 'category', [
        'formData' => [],
    ]);

    $response->assertStatus(404);
});

it('returns 422 when the field is not an AiSelectField on the resource', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'tech';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postClassify($this, 'ai-articles', 'summary', [
        'formData' => [],
    ]);

    $response->assertStatus(422);
});
