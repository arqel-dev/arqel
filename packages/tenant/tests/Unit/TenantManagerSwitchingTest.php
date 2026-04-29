<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\SupportsTenantSwitching;
use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

beforeEach(function (): void {
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

it('returns empty available list when resolver lacks SupportsTenantSwitching', function (): void {
    $manager = new TenantManager(new class implements TenantResolver
    {
        public function resolve(Request $request): ?Model
        {
            return null;
        }

        public function modelClass(): string
        {
            return Tenant::class;
        }

        public function identifierFor(Model $tenant): string
        {
            return (string) $tenant->getKey();
        }

        public function findByIdentifier(string $value): ?Model
        {
            return null;
        }
    });

    expect($manager->availableFor($this->user))->toBe([]);
    expect($manager->canSwitchTo($this->user, new Tenant(['id' => 1])))->toBeFalse();
});

it('throws LogicException on switchTo when resolver lacks the contract', function (): void {
    $manager = new TenantManager(new class implements TenantResolver
    {
        public function resolve(Request $request): ?Model
        {
            return null;
        }

        public function modelClass(): string
        {
            return Tenant::class;
        }

        public function identifierFor(Model $tenant): string
        {
            return (string) $tenant->getKey();
        }

        public function findByIdentifier(string $value): ?Model
        {
            return null;
        }
    });

    $manager->switchTo($this->user, new Tenant(['id' => 1]));
})->throws(LogicException::class, 'does not implement SupportsTenantSwitching');

it('delegates availableFor / canSwitchTo / switchTo to a SupportsTenantSwitching resolver', function (): void {
    $tenantA = new Tenant(['id' => 1, 'name' => 'A']);
    $tenantB = new Tenant(['id' => 2, 'name' => 'B']);

    $resolver = new class($tenantA, $tenantB) implements SupportsTenantSwitching, TenantResolver
    {
        public ?Model $switched = null;

        public function __construct(private Model $a, private Model $b) {}

        public function resolve(Request $request): ?Model
        {
            return null;
        }

        public function modelClass(): string
        {
            return Tenant::class;
        }

        public function identifierFor(Model $tenant): string
        {
            return (string) $tenant->getKey();
        }

        public function findByIdentifier(string $value): ?Model
        {
            return null;
        }

        public function availableFor(Authenticatable $user): array
        {
            return [$this->a, $this->b];
        }

        public function canSwitchTo(Authenticatable $user, Model $tenant): bool
        {
            return in_array($tenant->getKey(), [$this->a->getKey(), $this->b->getKey()], true);
        }

        public function switchTo(Authenticatable $user, Model $tenant): void
        {
            $this->switched = $tenant;
        }
    };

    $manager = new TenantManager($resolver);

    expect($manager->availableFor($this->user))->toEqual([$tenantA, $tenantB]);
    expect($manager->canSwitchTo($this->user, $tenantB))->toBeTrue();
    expect($manager->canSwitchTo($this->user, new Tenant(['id' => 99])))->toBeFalse();

    $manager->switchTo($this->user, $tenantB);
    expect($resolver->switched)->toBe($tenantB);
    expect($manager->current())->toBe($tenantB);
});
