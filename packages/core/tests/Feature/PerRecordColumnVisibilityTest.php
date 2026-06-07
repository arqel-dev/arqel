<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * #182: `Column::canSee()` per-record visibility was never consulted
 * when the controller serialised a row, so a column gated by
 * `->canSee(fn ($record) => false)` still leaked its cell value into
 * every row of the Inertia payload. This is SECURITY-adjacent: it is
 * the mechanism apps use to redact a column per record (e.g. hide a
 * salary cell from rows the viewer may not see).
 *
 * Columns are duck-typed against `Arqel\Table\Column` (which exposes
 * `getName()` + `isVisibleFor(?Model)`) so `arqel-dev/core` keeps no
 * dep on `arqel-dev/table` — the dep edge runs the other way.
 *
 * The host environment has no `pdo_sqlite`, so we drive the private
 * `serializeRecord` through Reflection instead of going through
 * `buildIndexData` (which would need a paginator → a DB).
 */
final class FakeColumn
{
    /**
     * @param null|Closure(?Model):bool $canSee
     */
    public function __construct(
        public string $name,
        public ?Closure $canSee = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleFor(?Model $record = null): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return ($this->canSee)($record);
    }
}

final class ColumnVisibilityResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'column-visibility';

    public function fields(): array
    {
        return [];
    }
}

/**
 * @param array<int, mixed> $columns
 *
 * @return array<string, mixed>
 */
function serializeRowWithColumns(Model $record, array $columns): array
{
    $builder = app(InertiaDataBuilder::class);
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('serializeRecord');
    $method->setAccessible(true);

    /** @var array<string, mixed> $payload */
    $payload = $method->invoke($builder, $record, new ColumnVisibilityResource, [], null, $columns);

    return $payload;
}

it('redacts the cell of a column whose canSee() returns false (#182)', function (): void {
    $record = new Stub(['name' => 'visible-value', 'secret' => 'top-secret']);

    $payload = serializeRowWithColumns($record, [
        new FakeColumn(name: 'name'),
        new FakeColumn(name: 'secret', canSee: fn (?Model $r): bool => false),
    ]);

    expect($payload)->not->toHaveKey('secret')
        ->and($payload)->toHaveKey('name')
        ->and($payload['name'])->toBe('visible-value');
});

it('keeps every cell when no column declares canSee (no regression, #182)', function (): void {
    $record = new Stub(['name' => 'visible-value', 'secret' => 'top-secret']);

    $payload = serializeRowWithColumns($record, [
        new FakeColumn(name: 'name'),
        new FakeColumn(name: 'secret'),
    ]);

    expect($payload)->toHaveKey('name')
        ->and($payload)->toHaveKey('secret')
        ->and($payload['secret'])->toBe('top-secret');
});

it('evaluates column visibility against the actual record (#182)', function (): void {
    $alice = new Stub(['name' => 'alice', 'secret' => 'a']);
    $bob = new Stub(['name' => 'bob', 'secret' => 'b']);

    $columns = [
        new FakeColumn(name: 'name'),
        new FakeColumn(name: 'secret', canSee: fn (?Model $r): bool => $r instanceof Model && $r->name === 'alice'),
    ];

    expect(serializeRowWithColumns($alice, $columns))->toHaveKey('secret')
        ->and(serializeRowWithColumns($bob, $columns))->not->toHaveKey('secret');
});

it('still emits arqel meta alongside redaction (#182)', function (): void {
    $record = new Stub(['name' => 'visible-value', 'secret' => 'top-secret']);

    $payload = serializeRowWithColumns($record, [
        new FakeColumn(name: 'secret', canSee: fn (?Model $r): bool => false),
    ]);

    expect($payload)->toHaveKey('arqel')
        ->and($payload)->not->toHaveKey('secret');
});
