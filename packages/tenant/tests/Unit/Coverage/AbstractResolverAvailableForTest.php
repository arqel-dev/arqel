<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\AbstractTenantResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Concrete subclass exposing `switchableRelationName()` for tests.
 */
final class CoverageResolver extends AbstractTenantResolver
{
    public ?string $relationName = 'tenants';

    public function resolve(Request $request): ?Model
    {
        return null;
    }

    protected function switchableRelationName(): string
    {
        return $this->relationName ?? 'tenants';
    }
}

function makeCoverageResolver(): CoverageResolver
{
    return new CoverageResolver(Tenant::class, 'id');
}

/**
 * TENANT-014 coverage gap: the default impl on AbstractTenantResolver was
 * never directly exercised. Existing TenantManagerSwitchingTest only covered
 * the manager's delegation; resolver internals (non-Model user, missing
 * relation, mixed-type Collection filtering) lived in dead lines.
 */
it('returns [] when the authenticatable is not an Eloquent Model', function (): void {
    $resolver = makeCoverageResolver();

    $auth = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return 'x';
        }

        public function getAuthPasswordName(): string
        {
            return 'p';
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

    expect($resolver->availableFor($auth))->toBe([]);
});

it('returns [] when the relation method does not exist on the user model', function (): void {
    $resolver = makeCoverageResolver();
    $resolver->relationName = 'totally_made_up_relation';

    $userModel = new AuthUser;
    $userModel->id = 1;

    expect($resolver->availableFor($userModel))->toBe([]);
});

it('filters a Collection of mixed types down to modelClass instances only', function (): void {
    $resolver = makeCoverageResolver();
    $resolver->relationName = 'preloaded';

    $tenantA = new Tenant(['id' => 1, 'name' => 'A']);
    $tenantB = new Tenant(['id' => 2, 'name' => 'B']);

    $userModel = new class extends AuthUser
    {
        public Collection $preloaded;
    };
    $userModel->id = 1;
    $userModel->preloaded = new Collection([
        $tenantA,
        'not a model',
        new stdClass,
        $tenantB,
        42,
    ]);

    $available = $resolver->availableFor($userModel);

    expect($available)->toHaveCount(2);
    expect($available[0])->toBe($tenantA);
    expect($available[1])->toBe($tenantB);
});

it('honors a plain array property too', function (): void {
    $resolver = makeCoverageResolver();
    $resolver->relationName = 'plainList';

    $tenant = new Tenant(['id' => 1]);

    $userModel = new class extends AuthUser
    {
        public array $plainList;
    };
    $userModel->id = 1;
    $userModel->plainList = [$tenant, 'junk'];

    $available = $resolver->availableFor($userModel);

    expect($available)->toEqual([$tenant]);
});
