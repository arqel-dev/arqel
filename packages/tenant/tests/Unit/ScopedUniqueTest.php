<?php

declare(strict_types=1);

use Arqel\Tenant\Rules\ScopedUnique;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * In-memory query builder stub that records every where() call
 * and returns a configurable `exists()` result. Lets us drive
 * the ScopedUnique rule without `pdo_sqlite`.
 */
function recordingQueryBuilder(bool $existsResult, array &$captured): object
{
    return new class($existsResult, $captured)
    {
        /** @param  array<int, array{column: string, op: string, value: mixed}>  $captured */
        public function __construct(
            private readonly bool $existsResult,
            private array &$captured,
        ) {}

        public function where(string $column, mixed $opOrValue, mixed $value = null): static
        {
            if ($value === null) {
                $this->captured[] = ['column' => $column, 'op' => '=', 'value' => $opOrValue];
            } else {
                $this->captured[] = ['column' => $column, 'op' => (string) $opOrValue, 'value' => $value];
            }

            return $this;
        }

        public function exists(): bool
        {
            return $this->existsResult;
        }
    };
}

function fakeConnectionResolver(object $queryBuilder, ?string &$tableSeen = null): ConnectionResolverInterface
{
    $resolver = new class($queryBuilder, $tableSeen) implements ConnectionResolverInterface
    {
        public function __construct(
            private readonly object $queryBuilder,
            private ?string &$tableSeen,
        ) {}

        public function connection($name = null): object
        {
            return new class($this->queryBuilder, $this->tableSeen)
            {
                public function __construct(
                    private readonly object $queryBuilder,
                    private ?string &$tableSeen,
                ) {}

                public function table(string $table): object
                {
                    $this->tableSeen = $table;

                    return $this->queryBuilder;
                }
            };
        }

        public function getDefaultConnection(): string
        {
            return 'testing';
        }

        public function setDefaultConnection($name): void {}
    };

    return $resolver;
}

beforeEach(function (): void {
    config([
        'arqel.tenancy.foreign_key' => 'tenant_id',
        'arqel.tenancy.model' => Tenant::class,
    ]);

    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->forget();
});

it('passes when no duplicate exists in the current tenant', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $captured = [];
    $tableSeen = null;
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured), $tableSeen),
    );

    $failed = false;
    $rule = new ScopedUnique('posts', 'slug');
    $rule->validate('slug', 'hello', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse()
        ->and($tableSeen)->toBe('posts');
});

it('fails when a duplicate row is found', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(true, $captured)),
    );

    $messages = [];
    $rule = new ScopedUnique('posts', 'slug');
    $rule->validate('slug', 'hello', function (string $msg) use (&$messages): void {
        $messages[] = $msg;
    });

    expect($messages)->not->toBeEmpty();
});

it('adds the tenant_id where clause when a tenant is current', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 42]));

    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured)),
    );

    (new ScopedUnique('posts', 'slug'))->validate('slug', 'hello', fn () => null);

    $tenantWhere = collect($captured)->firstWhere('column', 'tenant_id');

    expect($tenantWhere)->not->toBeNull()
        ->and($tenantWhere['value'])->toBe(42);
});

it('skips the tenant_id clause when no tenant is current (global fallback)', function (): void {
    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured)),
    );

    (new ScopedUnique('posts', 'slug'))->validate('slug', 'hello', fn () => null);

    $tenantWhere = collect($captured)->firstWhere('column', 'tenant_id');

    expect($tenantWhere)->toBeNull();
});

it('appends the ignore clause when ignore is provided', function (): void {
    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured)),
    );

    (new ScopedUnique('posts', 'slug', ignore: 99))
        ->validate('slug', 'hello', fn () => null);

    $ignoreWhere = collect($captured)->firstWhere('column', 'id');

    expect($ignoreWhere)->not->toBeNull()
        ->and($ignoreWhere['op'])->toBe('!=')
        ->and($ignoreWhere['value'])->toBe(99);
});

it('honours custom ignoreColumn', function (): void {
    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured)),
    );

    (new ScopedUnique('posts', 'slug', ignore: 'abc-uuid', ignoreColumn: 'uuid'))
        ->validate('slug', 'hello', fn () => null);

    $ignoreWhere = collect($captured)->firstWhere('column', 'uuid');

    expect($ignoreWhere)->not->toBeNull()
        ->and($ignoreWhere['value'])->toBe('abc-uuid');
});

it('honours an explicit tenantForeignKey override', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $captured = [];
    app()->instance(
        ConnectionResolverInterface::class,
        fakeConnectionResolver(recordingQueryBuilder(false, $captured)),
    );

    (new ScopedUnique('posts', 'slug', tenantForeignKey: 'workspace_id'))
        ->validate('slug', 'hello', fn () => null);

    $where = collect($captured)->firstWhere('column', 'workspace_id');

    expect($where)->not->toBeNull()
        ->and($where['value'])->toBe(7);
});

// Note: the "no-DB-resolver-bound" defer-to-other-rules guard
// is exercised in the implementation but not unit-tested here:
// Testbench always boots a `db` container slot, so simulating the
// missing-binding requires fully unbinding the framework's
// DatabaseServiceProvider — out of scope for a per-rule unit test.
