<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * #206: `Column::getState()`/`formatState()` (honouring `getStateUsing`/
 * `formatStateUsing`, the engine behind `ComputedColumn`) were invoked
 * nowhere outside their own unit tests. `serializeRecord` built the row
 * payload straight from `$record->toArray()`, so a `ComputedColumn`
 * rendered BLANK in the live table (no backing attribute) and a
 * `formatStateUsing` column rendered its RAW attribute.
 *
 * The fix resolves each state-resolver column through the Column
 * pipeline and injects the resolved value under the column's name —
 * the exact key the React `DataTable` reads (`row[col.name]`).
 *
 * Columns are duck-typed against `Arqel\Table\Column`
 * (`getName()`/`usesStateResolver()`/`getState()`/`formatState()`) so
 * `arqel-dev/core` keeps no dep on `arqel-dev/table`. The host has no
 * paginator here, so we drive the private `serializeRecord` via
 * Reflection (mirroring PerRecordColumnVisibilityTest, #182).
 */
final class StateResolverColumnStub
{
    /**
     * @param null|Closure(?Model):mixed $getStateUsing
     * @param null|Closure(mixed, ?Model):mixed $formatStateUsing
     */
    public function __construct(
        public string $name,
        public ?Closure $getStateUsing = null,
        public ?Closure $formatStateUsing = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleFor(?Model $record = null): bool
    {
        return true;
    }

    public function usesStateResolver(): bool
    {
        return $this->getStateUsing !== null || $this->formatStateUsing !== null;
    }

    public function getState(?Model $record): mixed
    {
        if ($this->getStateUsing !== null) {
            return ($this->getStateUsing)($record);
        }

        return $record?->getAttribute($this->name);
    }

    public function formatState(mixed $state, ?Model $record = null): mixed
    {
        if ($this->formatStateUsing !== null) {
            return ($this->formatStateUsing)($state, $record);
        }

        return $state;
    }
}

final class StateResolverResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'state-resolver';

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
function serializeRowWithStateColumns(Model $record, array $columns): array
{
    $builder = app(InertiaDataBuilder::class);
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('serializeRecord');
    $method->setAccessible(true);

    /** @var array<string, mixed> $payload */
    $payload = $method->invoke($builder, $record, new StateResolverResource, [], null, $columns);

    return $payload;
}

it('injects the getStateUsing value for a ComputedColumn-style column (#206)', function (): void {
    $record = new Stub(['title' => 'Hello']);

    $payload = serializeRowWithStateColumns($record, [
        new StateResolverColumnStub(
            name: 'full_title',
            getStateUsing: fn (?Model $r): string => 'COMPUTED:'.($r?->getAttribute('title') ?? ''),
        ),
    ]);

    expect($payload)->toHaveKey('full_title')
        ->and($payload['full_title'])->toBe('COMPUTED:Hello');
});

it('injects the formatStateUsing value over the raw attribute (#206)', function (): void {
    $record = new Stub(['title' => 'hello']);

    $payload = serializeRowWithStateColumns($record, [
        new StateResolverColumnStub(
            name: 'title',
            formatStateUsing: fn (mixed $state): string => strtoupper((string) $state),
        ),
    ]);

    expect($payload['title'])->toBe('HELLO');
});

it('composes getStateUsing then formatStateUsing in order (#206)', function (): void {
    $record = new Stub(['title' => 'world']);

    $payload = serializeRowWithStateColumns($record, [
        new StateResolverColumnStub(
            name: 'badge',
            getStateUsing: fn (?Model $r): string => (string) $r?->getAttribute('title'),
            formatStateUsing: fn (mixed $state): string => '['.strtoupper((string) $state).']',
        ),
    ]);

    expect($payload['badge'])->toBe('[WORLD]');
});

it('leaves a plain column (no state resolver) reading the raw attribute (no regression, #206)', function (): void {
    $record = new Stub(['title' => 'raw-value']);

    $payload = serializeRowWithStateColumns($record, [
        new StateResolverColumnStub(name: 'title'),
    ]);

    expect($payload['title'])->toBe('raw-value');
});
