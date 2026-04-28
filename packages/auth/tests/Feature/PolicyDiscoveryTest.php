<?php

declare(strict_types=1);

use Arqel\Auth\PolicyDiscovery;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\BlogPostResource;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\OrphanResource;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\OverridePolicyResource;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\Policies\BlogPostPolicy;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\Policies\CustomBlogPostPolicy;

beforeEach(function (): void {
    $this->discovery = app(PolicyDiscovery::class);
});

it('auto-registers a Policy guessed from the model namespace', function (): void {
    $result = $this->discovery->autoRegisterPoliciesFor([BlogPostResource::class]);

    expect($result['registered'])->toHaveCount(1)
        ->and($result['missing'])->toBe([])
        ->and(array_values($result['registered'])[0])->toBe(BlogPostPolicy::class);
});

it('honours an explicit $policy override on the resource', function (): void {
    $result = $this->discovery->autoRegisterPoliciesFor([OverridePolicyResource::class]);

    expect(array_values($result['registered'])[0])->toBe(CustomBlogPostPolicy::class);
});

it('flags resources without a Policy as missing', function (): void {
    $result = $this->discovery->autoRegisterPoliciesFor([OrphanResource::class]);

    expect($result['registered'])->toBe([])
        ->and($result['missing'])->toBe([OrphanResource::class]);
});

it('skips resource classes that do not exist', function (): void {
    $result = $this->discovery->autoRegisterPoliciesFor(['App\\NotARealResource']);

    expect($result['registered'])->toBe([])
        ->and($result['missing'])->toBe([]);
});
