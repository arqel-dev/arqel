<?php

declare(strict_types=1);

use Arqel\Marketplace\MarketplaceServiceProvider;

it('boots the marketplace service provider', function (): void {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(MarketplaceServiceProvider::class);
});

it('exposes the arqel-marketplace config', function (): void {
    expect(config('arqel-marketplace.enabled'))->toBeTrue();
    expect(config('arqel-marketplace.route_prefix'))->toBe('api/marketplace');
    expect(config('arqel-marketplace.pagination'))->toBe(20);
    expect(config('arqel-marketplace.submission_review_required'))->toBeTrue();
});
