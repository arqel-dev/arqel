<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;
use Arqel\Ai\Tests\Fixtures\FakeAiExtractResource;
use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\Fixtures\FormOnlyAiExtractResource;
use Arqel\Ai\Tests\Fixtures\LimitThrowingProvider;
use Arqel\Ai\Tests\Fixtures\ThrowingProvider;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;

function authedUserForExtract(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 'extract@arqel.dev']);

    return $user;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function postExtract(TestCase $case, string $resourceSlug, string $field, array $payload): TestResponse
{
    return $case->actingAs(authedUserForExtract())->postJson(
        "/admin/{$resourceSlug}/fields/{$field}/extract",
        $payload,
    );
}

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('returns 200 with extracted JSON on the happy path', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiExtractResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"invoice_number":"INV-42","date":"2026-04-30"}';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postExtract($this, 'ai-invoices', 'extracted', [
        'sourceText' => 'Invoice #INV-42 issued on 2026-04-30',
    ]);

    $response->assertOk();
    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    expect($body)->toBe([
        'extracted' => [
            'invoice_number' => 'INV-42',
            'date' => '2026-04-30',
        ],
    ]);
});

it('returns 404 when the resource slug is not registered', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{}';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postExtract($this, 'unknown', 'extracted', [
        'sourceText' => 'irrelevant',
    ]);

    $response->assertStatus(404);
});

it('returns 422 when the field is not an AiExtractField on the resource', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{}';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postExtract($this, 'ai-articles', 'summary', [
        'sourceText' => 'irrelevant',
    ]);

    $response->assertStatus(422);
});

it('returns 422 when the AI response cannot be parsed as JSON', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiExtractResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'I cannot extract that, sorry.';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    /** @var TestCase $this */
    $response = postExtract($this, 'ai-invoices', 'extracted', [
        'sourceText' => 'Invoice #INV-42',
    ]);

    $response->assertStatus(422);
    expect((array) $response->json())->toHaveKey('message');
});

it('returns 422 with a generic message and never reflects the upstream body when the provider throws an AiException (#205 follow-up)', function (): void {
    Exceptions::fake();

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiExtractResource::class);
    app()->instance(AiManager::class, new AiManager([
        'fake' => new ThrowingProvider('fake', 'OpenAI API error (500): SECRET_UPSTREAM_BODY'),
    ]));

    /** @var TestCase $this */
    $response = postExtract($this, 'ai-invoices', 'extracted', [
        'sourceText' => 'Invoice #INV-42',
    ]);

    $response->assertStatus(422)
        ->assertExactJson(['message' => 'AI provider request failed']);

    expect($response->getContent())->not->toContain('SECRET_UPSTREAM_BODY');

    Exceptions::assertReported(AiException::class);
});

it('preserves the real DailyLimitExceeded message so the user knows they hit a limit', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiExtractResource::class);
    app()->instance(AiManager::class, new AiManager([
        'fake' => new LimitThrowingProvider('fake', new DailyLimitExceeded('Daily AI limit of $5 exceeded')),
    ]));

    /** @var TestCase $this */
    $response = postExtract($this, 'ai-invoices', 'extracted', [
        'sourceText' => 'Invoice #INV-42',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Daily AI limit of $5 exceeded']);
});

it('resolves an AiExtractField declared only inside form() (#104)', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FormOnlyAiExtractResource::class);

    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"invoice_number":"INV-42","date":"2026-04-30"}';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    // Before the fix this 422'd because the controller iterated fields()
    // (empty here) instead of effectiveFields() (the form's field list).
    /** @var TestCase $this */
    $response = postExtract($this, 'form-only-ai-invoices', 'extracted', [
        'sourceText' => 'Invoice #INV-42 issued on 2026-04-30',
    ]);

    $response->assertOk();
    /** @var array<string, mixed> $body */
    $body = (array) $response->json();
    expect($body)->toBe([
        'extracted' => [
            'invoice_number' => 'INV-42',
            'date' => '2026-04-30',
        ],
    ]);
});
