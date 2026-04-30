<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandRegistry;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function (): void {
    $this->registry = new CommandRegistry;

    $this->user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };
});

it('drops requiresAuth=true commands when user is null', function (): void {
    $this->registry->register(new Command(
        id: 'logout',
        label: 'Log out',
        url: '/logout',
        requiresAuth: true,
    ));

    expect($this->registry->resolveFor(null, ''))->toBe([]);
});

it('includes requiresAuth=true commands when user is present', function (): void {
    $this->registry->register(new Command(
        id: 'logout',
        label: 'Log out',
        url: '/logout',
        requiresAuth: true,
    ));

    $resolved = $this->registry->resolveFor($this->user, '');

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->id)->toBe('logout');
});

it('includes hideForAuthenticated=true commands when user is null', function (): void {
    $this->registry->register(new Command(
        id: 'login',
        label: 'Log in',
        url: '/login',
        hideForAuthenticated: true,
    ));

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->id)->toBe('login');
});

it('drops hideForAuthenticated=true commands when user is present', function (): void {
    $this->registry->register(new Command(
        id: 'login',
        label: 'Log in',
        url: '/login',
        hideForAuthenticated: true,
    ));

    expect($this->registry->resolveFor($this->user, ''))->toBe([]);
});

it('keeps commands with both flags null visible to everyone', function (): void {
    $this->registry->register(new Command(
        id: 'public',
        label: 'Public command',
        url: '/public',
    ));

    expect($this->registry->resolveFor(null, ''))->toHaveCount(1)
        ->and($this->registry->resolveFor($this->user, ''))->toHaveCount(1);
});
