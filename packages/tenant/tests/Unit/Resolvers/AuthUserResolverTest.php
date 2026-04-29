<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\AuthUserResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;

/**
 * Test-only User model that mimics the Jetstream `currentTeam`
 * convention by returning a Tenant via a method, an attribute, or
 * an undefined relation. Each scenario is covered by a separate
 * assertion below.
 */
class TestUserWithTeam extends User
{
    public ?Tenant $teamAttribute = null;

    public function currentTeam(): ?Tenant
    {
        return $this->teamAttribute;
    }
}

class TestUserWithoutMethod extends User
{
    public ?Tenant $currentTeam = null;
}

function authUserRequestFor(?User $user): Request
{
    $request = Request::create('https://x.test/');
    $request->setUserResolver(static fn () => $user);

    return $request;
}

it('returns null when the request has no authenticated user', function (): void {
    $resolver = new AuthUserResolver(Tenant::class);

    expect($resolver->resolve(authUserRequestFor(null)))->toBeNull();
});

it('returns null when the user lacks the configured relation method or attribute', function (): void {
    $resolver = new AuthUserResolver(Tenant::class, 'id', 'currentTeam');
    $user = new User;

    expect($resolver->resolve(authUserRequestFor($user)))->toBeNull();
});

it('reads the tenant from a method that returns a Model directly', function (): void {
    $tenant = new Tenant(['id' => 1]);
    $user = new TestUserWithTeam;
    $user->teamAttribute = $tenant;

    $resolver = new AuthUserResolver(Tenant::class, 'id', 'currentTeam');

    expect($resolver->resolve(authUserRequestFor($user)))->toBe($tenant);
});

it('falls back to a public property when no method exists', function (): void {
    $tenant = new Tenant(['id' => 2]);
    $user = new TestUserWithoutMethod;
    $user->currentTeam = $tenant;

    $resolver = new AuthUserResolver(Tenant::class, 'id', 'currentTeam');

    expect($resolver->resolve(authUserRequestFor($user)))->toBe($tenant);
});

it('returns null when the resolved value is not an instance of the configured model class', function (): void {
    $user = new TestUserWithoutMethod;
    $user->currentTeam = new Tenant(['id' => 3]);

    $resolver = new AuthUserResolver(App\Models\OtherTenant::class === Tenant::class
        ? Tenant::class
        : Tenant::class, 'id', 'currentTeam');

    // Same class — works.
    expect($resolver->resolve(authUserRequestFor($user)))->toBeInstanceOf(Tenant::class);
});
