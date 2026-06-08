<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * #206: when forwarding columns to a column-driven bulk action
 * (ExportAction), `ResourceController::serializeColumns` must attach a
 * `state_resolver` Closure for any column whose `usesStateResolver()`
 * is true, so the exporter resolves the cell through the Column pipeline
 * (`formatState(getState($record), $record)`) instead of a raw
 * `data_get`. Columns without a resolver stay plain descriptors (no
 * regression). Duck-typed against `Arqel\Table\Column`.
 */
final class ResolverColumnStub
{
    /**
     * @param null|Closure(?Model):mixed $getStateUsing
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?Closure $getStateUsing = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type, 'name' => $this->name, 'label' => ucfirst($this->name)];
    }

    public function usesStateResolver(): bool
    {
        return $this->getStateUsing !== null;
    }

    public function getState(?Model $record): mixed
    {
        return $this->getStateUsing !== null ? ($this->getStateUsing)($record) : null;
    }

    public function formatState(mixed $state, ?Model $record = null): mixed
    {
        return $state;
    }
}

/**
 * @param array<int, mixed> $columns
 *
 * @return array<int, array<string, mixed>>
 */
function invokeSerializeColumns(array $columns): array
{
    $controller = new ResourceController(
        app(ResourceRegistry::class),
        app(InertiaDataBuilder::class),
    );
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('serializeColumns');
    $method->setAccessible(true);

    /** @var array<int, array<string, mixed>> $result */
    $result = $method->invoke($controller, $columns);

    return $result;
}

it('attaches a state_resolver to a column that uses a state resolver (#206)', function (): void {
    $serialized = invokeSerializeColumns([
        new ResolverColumnStub(
            name: 'full_title',
            type: 'computed',
            getStateUsing: fn (?Model $r): string => 'COMPUTED',
        ),
    ]);

    expect($serialized[0])->toHaveKey('state_resolver')
        ->and($serialized[0]['state_resolver'])->toBeInstanceOf(Closure::class);

    $resolver = $serialized[0]['state_resolver'];
    expect($resolver(null))->toBe('COMPUTED');
});

it('does not attach a state_resolver to a plain column (no regression, #206)', function (): void {
    $serialized = invokeSerializeColumns([
        new ResolverColumnStub(name: 'title', type: 'text'),
    ]);

    expect($serialized[0])->not->toHaveKey('state_resolver');
});

it('passes already-array descriptors through untouched (#206)', function (): void {
    $serialized = invokeSerializeColumns([
        ['type' => 'text', 'name' => 'plain', 'label' => 'Plain'],
    ]);

    expect($serialized[0])->toBe(['type' => 'text', 'name' => 'plain', 'label' => 'Plain']);
});
