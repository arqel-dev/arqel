<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\Fixtures\FakeAiTranslateResource;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Testing\TestResponse;

function authedUserForTranslate(): AuthUser
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
function postTranslate(TestCase $case, string $resourceSlug, string $field, array $payload): TestResponse
{
    return $case->actingAs(authedUserForTranslate())->postJson(
        "/admin/{$resourceSlug}/fields/{$field}/translate",
        $payload,
    );
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with translations on the happy path', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiTranslateResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postTranslate($this, 'ai-pages', 'description', [
        'sourceLanguage' => 'en',
        'sourceText' => 'Hello world',
        'targetLanguages' => ['pt-BR', 'es'],
    ]);

    $response->assertOk();
    /** @var array<string, array<string, string>> $body */
    $body = (array) $response->json();
    expect($body)->toHaveKey('translations');
    $translations = $body['translations'];
    expect($translations)->toHaveKeys(['pt-BR', 'es'])
        ->and($translations['pt-BR'])->toStartWith('echo:Translate the following text from en to pt-BR')
        ->and($translations['es'])->toStartWith('echo:Translate the following text from en to es');
});

it('returns 404 when the resource slug is not registered', function (): void {
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postTranslate($this, 'non-existent', 'description', [
        'sourceLanguage' => 'en',
        'sourceText' => 'x',
        'targetLanguages' => ['pt-BR'],
    ]);

    $response->assertStatus(404);
});

it('returns 422 when the field is not an AiTranslateField on the resource', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postTranslate($this, 'ai-articles', 'summary', [
        'sourceLanguage' => 'en',
        'sourceText' => 'x',
        'targetLanguages' => ['pt-BR'],
    ]);

    $response->assertStatus(422);
});
