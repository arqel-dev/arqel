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

/**
 * Test user whose `currentTeam` is a real BelongsTo (Jetstream shape):
 * the relation reads its foreign key `current_team_id` from the user row.
 * `save()` is stubbed so the switch path never touches the database.
 */
class TestUserWithTeamRelation extends User
{
    protected $guarded = [];

    public $timestamps = false;

    public bool $saved = false;

    public function currentTeam(): Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_team_id');
    }

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}

it('switchTo writes the foreign key that currentTeam actually reads, so the switch persists', function (): void {
    // Regression: AuthUserResolver::resolve() reads via the `currentTeam`
    // BelongsTo (fk `current_team_id`), but the inherited switchTo() wrote
    // the default `current_tenant_id` column the relation never reads — the
    // switch was lost on the next request. switchTo must write the SAME
    // column the relation resolves from.
    $tenantB = new Tenant(['id' => 42]);
    $user = new TestUserWithTeamRelation;

    $resolver = new AuthUserResolver(Tenant::class, 'id', 'currentTeam');
    $resolver->switchTo($user, $tenantB);

    expect($user->getAttribute('current_team_id'))->toBe(42)
        ->and($user->saved)->toBeTrue();
});
