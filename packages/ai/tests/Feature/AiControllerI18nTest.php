<?php

declare(strict_types=1);

use Arqel\Ai\Tests\Fixtures\FakeAiResource;
use Arqel\Ai\Tests\TestCase;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Round 9 i18n regression suite for the AI field endpoints.
 *
 * Every user-facing JSON `message` returned by the AI controllers must be
 * routed through `arqel::messages.ai.*` so it honours the request locale.
 * These tests pin both the English value (accessible-name stability, so the
 * existing `assertExactJson` consumers keep passing) and the pt_BR
 * translation (so the strings are actually localized, not just wrapped).
 */
function i18nUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    return $user;
}

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
});

it('localizes the 403 forbidden message in pt_BR', function (): void {
    app()->setLocale('pt_BR');
    Gate::define('use-ai', fn (): bool => false);

    /** @var TestCase $this */
    $response = $this->actingAs(i18nUser())->postJson(
        '/admin/ai-articles/fields/summary/generate',
        ['formData' => []],
    );

    $response->assertStatus(403)
        ->assertExactJson(['message' => __('arqel::messages.ai.forbidden', [], 'pt_BR')]);

    expect(__('arqel::messages.ai.forbidden', [], 'en'))->toBe('Forbidden');
    expect(__('arqel::messages.ai.forbidden', [], 'pt_BR'))->not->toBe('arqel::messages.ai.forbidden');
});

it('localizes the 404 resource-not-registered message and interpolates the slug', function (): void {
    app()->setLocale('pt_BR');

    /** @var TestCase $this */
    $response = $this->actingAs(i18nUser())->postJson(
        '/admin/missing-slug/fields/summary/generate',
        ['formData' => []],
    );

    $response->assertStatus(404)
        ->assertExactJson([
            'message' => __('arqel::messages.ai.resource_not_registered', ['resource' => 'missing-slug'], 'pt_BR'),
        ]);

    // English value preserves the original hardcoded literal exactly.
    expect(__('arqel::messages.ai.resource_not_registered', ['resource' => 'posts'], 'en'))
        ->toBe('Resource [posts] not registered');
    // pt_BR is actually translated.
    expect(__('arqel::messages.ai.resource_not_registered', ['resource' => 'posts'], 'pt_BR'))
        ->toContain('não registrado');
});

it('localizes the 422 field-not-found message while keeping the English accessible name stable', function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->register(FakeAiResource::class);

    app()->setLocale('pt_BR');

    /** @var TestCase $this */
    $response = $this->actingAs(i18nUser())->postJson(
        '/admin/ai-articles/fields/does-not-exist/generate',
        ['formData' => []],
    );

    $response->assertStatus(422)
        ->assertExactJson([
            'message' => __('arqel::messages.ai.field_not_found', [
                'type' => 'AiTextField',
                'field' => 'does-not-exist',
                'resource' => 'ai-articles',
            ], 'pt_BR'),
        ]);

    // The English value reconstructs the original hardcoded literal verbatim.
    expect(__('arqel::messages.ai.field_not_found', [
        'type' => 'AiTextField',
        'field' => 'body',
        'resource' => 'posts',
    ], 'en'))->toBe('AiTextField [body] not found on resource [posts]');
});

it('localizes the analyze-image source-required validation message', function (): void {
    app()->setLocale('pt_BR');

    /** @var TestCase $this */
    $response = $this->actingAs(i18nUser())->postJson(
        '/admin/ai-articles/fields/cover/analyze-image',
        [],
    );

    $response->assertStatus(422)
        ->assertExactJson(['message' => __('arqel::messages.ai.image_source_required', [], 'pt_BR')]);

    expect(__('arqel::messages.ai.image_source_required', [], 'en'))
        ->toBe('Either imageUrl or imageBase64 must be provided');
    expect(__('arqel::messages.ai.image_source_required', [], 'pt_BR'))
        ->not->toBe('arqel::messages.ai.image_source_required');
});

it('keeps the provider-failed value identical in English (accessible-name stability)', function (): void {
    // Five controller tests `assertExactJson(['message' => 'AI provider request failed'])`;
    // the localized key MUST resolve to that exact literal under the default locale.
    expect(__('arqel::messages.ai.provider_failed', [], 'en'))->toBe('AI provider request failed');
    expect(__('arqel::messages.ai.provider_failed', [], 'pt_BR'))
        ->not->toBe('arqel::messages.ai.provider_failed');
});

it('localizes the field-resolution-failed and registry messages without leaking exception detail', function (): void {
    // These are translated and must not echo a raw exception message anymore.
    expect(__('arqel::messages.ai.field_resolution_failed', [], 'en'))
        ->not->toContain(':');
    expect(__('arqel::messages.ai.registry_unbound', [], 'pt_BR'))
        ->not->toBe('arqel::messages.ai.registry_unbound');
    expect(__('arqel::messages.ai.registry_contract_mismatch', [], 'pt_BR'))
        ->not->toBe('arqel::messages.ai.registry_contract_mismatch');
});
