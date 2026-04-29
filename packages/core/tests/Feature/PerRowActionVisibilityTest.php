<?php

declare(strict_types=1);

use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Database\Eloquent\Model;

/**
 * TABLE-007: per-row authorization of row actions. The server emits
 * a list of action *names* visible/executable for each record under
 * `arqel.actions`; the client filters the global action list against
 * it so the UI hides buttons that the policy/visibility predicates
 * would block.
 *
 * Duck-typed objects mimic `Arqel\Actions\Action` so we don't pull
 * `arqel/actions` as a dep of `arqel/core` (it goes the other way
 * around in the dep graph).
 *
 * The host environment does not ship with `pdo_sqlite`, so we drive
 * the private serializer via Reflection instead of going through
 * `buildIndexData` (which would need a paginator, which needs a DB).
 */
final class FakeRowAction
{
    public function __construct(
        public string $name,
        public bool $visible = true,
        public bool $executable = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleFor(mixed $record = null): bool
    {
        return $this->visible;
    }

    public function canBeExecutedBy(mixed $user = null, mixed $record = null): bool
    {
        return $this->executable;
    }
}

/**
 * @param array<int, mixed> $actions
 *
 * @return list<string>
 */
function resolveVisibleNames(array $actions, Model $record, mixed $user = null): array
{
    $builder = app(InertiaDataBuilder::class);
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('resolveVisibleActionNames');
    $method->setAccessible(true);

    /** @var list<string> $names */
    $names = $method->invoke($builder, $actions, $record, $user);

    return $names;
}

it('keeps actions whose isVisibleFor and canBeExecutedBy both pass', function (): void {
    $record = new Stub(['name' => 'first']);

    $names = resolveVisibleNames([
        new FakeRowAction(name: 'view'),
        new FakeRowAction(name: 'edit'),
        new FakeRowAction(name: 'delete'),
    ], $record);

    expect($names)->toBe(['view', 'edit', 'delete']);
});

it('drops actions when isVisibleFor returns false', function (): void {
    $record = new Stub(['name' => 'first']);

    $names = resolveVisibleNames([
        new FakeRowAction(name: 'view'),
        new FakeRowAction(name: 'archive', visible: false),
        new FakeRowAction(name: 'delete'),
    ], $record);

    expect($names)->toBe(['view', 'delete']);
});

it('drops actions when canBeExecutedBy returns false', function (): void {
    $record = new Stub(['name' => 'first']);

    $names = resolveVisibleNames([
        new FakeRowAction(name: 'view'),
        new FakeRowAction(name: 'delete', executable: false),
    ], $record);

    expect($names)->toBe(['view']);
});

it('evaluates visibility against the actual record', function (): void {
    $first = new Stub(['name' => 'first']);
    $second = new Stub(['name' => 'second']);

    $action = new class
    {
        public function getName(): string
        {
            return 'restore';
        }

        public function isVisibleFor(mixed $record = null): bool
        {
            return $record instanceof Model && $record->name === 'second';
        }

        public function canBeExecutedBy(mixed $user = null, mixed $record = null): bool
        {
            return true;
        }
    };

    expect(resolveVisibleNames([$action], $first))->toBe([])
        ->and(resolveVisibleNames([$action], $second))->toBe(['restore']);
});

it('silently skips entries that do not expose getName or are not objects', function (): void {
    $record = new Stub(['name' => 'first']);

    $names = resolveVisibleNames([
        new FakeRowAction(name: 'view'),
        new stdClass,                 // no getName
        'not-an-object',              // not even an object
        null,
    ], $record);

    expect($names)->toBe(['view']);
});
