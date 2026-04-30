<?php

declare(strict_types=1);

use Arqel\Tenant\Tests\Fixtures\FeatureGatedTenant;

it('hasFeature returns false when features attribute is null', function (): void {
    $tenant = new FeatureGatedTenant;

    expect($tenant->hasFeature('analytics'))->toBeFalse();
    expect($tenant->getFeatures())->toBe([]);
});

it('hasFeature returns false when features is an empty array', function (): void {
    $tenant = new FeatureGatedTenant(['features' => []]);

    expect($tenant->hasFeature('analytics'))->toBeFalse();
});

it('hasFeature returns true for enabled features and false for others', function (): void {
    $tenant = new FeatureGatedTenant(['features' => ['analytics']]);

    expect($tenant->hasFeature('analytics'))->toBeTrue();
    expect($tenant->hasFeature('reports'))->toBeFalse();
});

it('enableFeature dedupes already-enabled entries', function (): void {
    $tenant = new FeatureGatedTenant(['features' => ['analytics']]);

    $tenant->enableFeature('analytics');
    $tenant->enableFeature('analytics');
    $tenant->enableFeature('reports');

    expect($tenant->getFeatures())->toBe(['analytics', 'reports']);
});

it('disableFeature removes the entry and re-indexes', function (): void {
    $tenant = new FeatureGatedTenant(['features' => ['analytics', 'reports', 'webhooks']]);

    $tenant->disableFeature('reports');

    expect($tenant->getFeatures())->toBe(['analytics', 'webhooks']);
});

it('disableFeature is a no-op when feature is not enabled', function (): void {
    $tenant = new FeatureGatedTenant(['features' => ['analytics']]);

    $tenant->disableFeature('reports');

    expect($tenant->getFeatures())->toBe(['analytics']);
});

it('getFeatures filters non-string and empty entries from corrupt data', function (): void {
    $tenant = new FeatureGatedTenant(['features' => ['analytics', '', 0, null, 'reports', 'analytics']]);

    expect($tenant->getFeatures())->toBe(['analytics', 'reports']);
});

it('getFeatures returns empty list when features is a non-array scalar', function (): void {
    $tenant = new FeatureGatedTenant;
    // simulate corrupt legacy storage: non-array attribute
    $tenant->setRawAttributes(['features' => 'not-an-array']);

    expect($tenant->getFeatures())->toBe([]);
    expect($tenant->hasFeature('anything'))->toBeFalse();
});
