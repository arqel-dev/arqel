<?php

declare(strict_types=1);

use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;

/**
 * TENANT-014 coverage gap: nested `runFor` + the try/finally invariant
 * around BOTH `currentTenant` AND `resolved` flags. Existing tests cover
 * single-level restore-on-throw of `currentTenant` only.
 */
it('restores both currentTenant and resolved flag when callback throws', function (): void {
    $manager = new TenantManager;
    $sentinel = new Tenant(['id' => 99]);

    expect($manager->resolved())->toBeFalse();

    $caught = false;
    try {
        $manager->runFor($sentinel, function () {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
    expect($manager->current())->toBeNull();
    expect($manager->resolved())->toBeFalse();
});

it('unwinds nested runFor calls in LIFO order', function (): void {
    $manager = new TenantManager;
    $outer = new Tenant(['id' => 1]);
    $inner = new Tenant(['id' => 2]);

    $observed = [];
    $manager->runFor($outer, function () use ($manager, $inner, &$observed) {
        $observed[] = $manager->current()?->getKey();
        $manager->runFor($inner, function () use ($manager, &$observed) {
            $observed[] = $manager->current()?->getKey();
        });
        $observed[] = $manager->current()?->getKey();
    });

    expect($observed)->toBe([1, 2, 1]);
    expect($manager->current())->toBeNull();
});

it('preserves the previous tenant context across nested throws', function (): void {
    $manager = new TenantManager;
    $outer = new Tenant(['id' => 1]);
    $inner = new Tenant(['id' => 2]);

    $manager->runFor($outer, function () use ($manager, $inner) {
        try {
            $manager->runFor($inner, function () {
                throw new LogicException('inner boom');
            });
        } catch (LogicException) {
            // Inner unwound — outer context restored
        }

        expect($manager->current()?->getKey())->toBe(1);
    });

    expect($manager->current())->toBeNull();
});
