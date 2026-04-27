<?php

declare(strict_types=1);

use Arqel\Core\Panel\Panel;
use Arqel\Core\Panel\PanelNotFoundException;
use Arqel\Core\Panel\PanelRegistry;

beforeEach(function (): void {
    $this->registry = new PanelRegistry;
});

it('creates a panel on first call to panel()', function (): void {
    $panel = $this->registry->panel('admin');

    expect($panel)->toBeInstanceOf(Panel::class)
        ->and($panel->id)->toBe('admin')
        ->and($this->registry->has('admin'))->toBeTrue();
});

it('returns the same panel instance on repeated calls with the same id', function (): void {
    $first = $this->registry->panel('admin')->path('/a');
    $second = $this->registry->panel('admin');

    expect($second)->toBe($first)
        ->and($second->getPath())->toBe('/a');
});

it('keeps multiple panels independent', function (): void {
    $admin = $this->registry->panel('admin')->path('/admin');
    $customer = $this->registry->panel('customer')->path('/customer');

    expect($this->registry->all())
        ->toHaveCount(2)
        ->toContain($admin, $customer)
        ->and($admin->getPath())->toBe('/admin')
        ->and($customer->getPath())->toBe('/customer');
});

it('returns null for the current panel until one is set', function (): void {
    $this->registry->panel('admin');

    expect($this->registry->getCurrent())->toBeNull();
});

it('switches the current panel when setCurrent is called', function (): void {
    $admin = $this->registry->panel('admin');
    $this->registry->panel('customer');

    $this->registry->setCurrent('admin');

    expect($this->registry->getCurrent())->toBe($admin);
});

it('throws when setting an unknown panel as current', function (): void {
    $this->registry->setCurrent('missing');
})->throws(PanelNotFoundException::class, 'No panel registered with id [missing]');

it('clears all panels and the current pointer', function (): void {
    $this->registry->panel('admin');
    $this->registry->setCurrent('admin');

    $this->registry->clear();

    expect($this->registry->all())->toBe([])
        ->and($this->registry->getCurrent())->toBeNull()
        ->and($this->registry->has('admin'))->toBeFalse();
});
