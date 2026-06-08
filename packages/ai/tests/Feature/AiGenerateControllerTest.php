<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiManager;
use Arqel\Ai\CostTracker;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Models\AiUsage;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Arqel\Ai\Tests\Fixtures\FormOnlyAiTextResource;
use Arqel\Ai\Tests\Fixtures\LimitThrowingProvider;
use Arqel\Ai\Tests\Fixtures\ThrowingProvider;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;

function authedUser(): AuthUser
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
function postGenerate(TestCase $case, string $resourceSlug, string $field, array $payload): TestResponse
{
    return $case->actingAs(authedUser())->postJson(
        "/admin/{$resourceSlug}/fields/{$field}/generate",
        $payload,
    );
}

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with the generated text on happy path', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertOk()
        ->assertJson(['text' => 'echo:Summarize Hello world']);
});

it('returns 404 when the resource slug is not registered', function (): void {
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'non-existent', 'summary', ['formData' => []]);

    $response->assertStatus(404);
});

it('returns 422 when the field is not an AiTextField on the resource', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'title', ['formData' => []]);

    $response->assertStatus(422);
});

it('resolves an AiTextField declared only inside form() (#104)', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FormOnlyAiTextResource::class);
    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')]));

    // Before the fix this 422'd because the controller iterated fields()
    // (empty here) instead of effectiveFields() (the form's field list).
    /** @var TestCase $this */
    $response = postGenerate($this, 'form-only-ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertOk()
        ->assertJson(['text' => 'echo:Summarize Hello world']);
});

it('returns 422 with a generic message and never reflects the upstream body when the provider throws an AiException (#205 follow-up)', function (): void {
    Exceptions::fake();

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager([
        'fake' => new ThrowingProvider('fake', 'OpenAI API error (500): SECRET_UPSTREAM_BODY'),
    ]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertStatus(422)
        ->assertExactJson(['message' => 'AI provider request failed']);

    expect($response->getContent())->not->toContain('SECRET_UPSTREAM_BODY');

    Exceptions::assertReported(AiException::class);
});

it('preserves the real DailyLimitExceeded message so the user knows they hit a limit', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager([
        'fake' => new LimitThrowingProvider('fake', new DailyLimitExceeded('Daily AI limit of $5 exceeded')),
    ]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Daily AI limit of $5 exceeded']);
});

it('preserves the real UserLimitExceeded message so the user knows they hit a limit', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);
    app()->instance(AiManager::class, new AiManager([
        'fake' => new LimitThrowingProvider('fake', new UserLimitExceeded('User #1 daily AI limit of $2 exceeded')),
    ]));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'User #1 daily AI limit of $2 exceeded']);
});

it('returns 422 (not 500) when the daily cost limit is exceeded (#205)', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);

    config()->set('arqel-ai.default_provider', 'fake');
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 0.0001);
    AiUsage::query()->create([
        'user_id' => null, 'provider' => 'fake', 'model' => 'fake-model',
        'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 1.0,
    ]);

    app()->instance(AiManager::class, new AiManager(['fake' => new FakeProvider('fake')], new CostTracker, new AiCache));

    /** @var TestCase $this */
    $response = postGenerate($this, 'ai-articles', 'summary', ['formData' => ['title' => 'Hello world']]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message']);
});
