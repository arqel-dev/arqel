<?php

declare(strict_types=1);

use Arqel\Marketplace\Events\PluginSubmitted;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Event;

function submitter(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'submitter']);

    return $u;
}

function validSubmissionPayload(array $overrides = []): array
{
    return array_merge([
        'composer_package' => 'acme/awesome-plugin',
        'github_url' => 'https://github.com/acme/awesome-plugin',
        'type' => 'widget',
        'name' => 'Awesome Plugin',
        'description' => 'A really useful plugin that does many cool things for admin panels.',
    ], $overrides);
}

it('returns 201 on happy path and persists submission', function (): void {
    Event::fake();
    $user = submitter();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/submit', validSubmissionPayload());

    $response->assertCreated();
    expect($response->json('plugin.status'))->toBe('pending');
    expect($response->json('checks.passed'))->toBeTrue();

    $plugin = Plugin::query()->where('slug', 'awesome-plugin')->firstOrFail();
    expect($plugin->status)->toBe('pending');
    expect($plugin->submitted_by_user_id)->toBe($user->id);
    expect($plugin->submitted_at)->not->toBeNull();
    expect($plugin->submission_metadata)->toBeArray();

    Event::assertDispatched(PluginSubmitted::class);
});

it('returns 422 when composer package is invalid', function (): void {
    $user = submitter();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/submit', validSubmissionPayload([
            'composer_package' => 'INVALID NAME',
        ]));

    $response->assertStatus(422);
});

it('returns 422 when github_url is invalid', function (): void {
    $user = submitter();

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/submit', validSubmissionPayload([
            'github_url' => 'not-a-url',
        ]));

    $response->assertStatus(422);
});

it('returns 401 when unauthenticated', function (): void {
    $response = $this->postJson('/api/marketplace/plugins/submit', validSubmissionPayload());

    expect($response->status())->toBeIn([401, 302, 403]);
});

it('rejects duplicate slug as 422', function (): void {
    $user = submitter();

    Plugin::query()->create([
        'slug' => 'awesome-plugin',
        'name' => 'Existing',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/y',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/submit', validSubmissionPayload());

    $response->assertStatus(422);
});

it('populates submitted_by from authenticated user', function (): void {
    $user = submitter();

    $this->actingAs($user)
        ->postJson('/api/marketplace/plugins/submit', validSubmissionPayload([
            'name' => 'Other Plugin',
        ]))
        ->assertCreated();

    $plugin = Plugin::query()->where('slug', 'other-plugin')->firstOrFail();
    expect($plugin->submitted_by_user_id)->toBe($user->id);
});
