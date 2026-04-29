<?php

declare(strict_types=1);

use Arqel\Tenant\Events\TenantSwitched;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Foundation\Auth\User;

it('carries from/to/user and allows null `from`', function (): void {
    $to = new Tenant(['id' => 2]);
    $user = new User;

    $event = new TenantSwitched(from: null, to: $to, user: $user);

    expect($event->from)->toBeNull();
    expect($event->to)->toBe($to);
    expect($event->user)->toBe($user);
});

it('carries the previous tenant when present', function (): void {
    $from = new Tenant(['id' => 1]);
    $to = new Tenant(['id' => 2]);
    $user = new User;

    $event = new TenantSwitched(from: $from, to: $to, user: $user);

    expect($event->from)->toBe($from);
    expect($event->to)->toBe($to);
});
