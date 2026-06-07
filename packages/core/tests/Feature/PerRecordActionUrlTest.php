<?php

declare(strict_types=1);

use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Illuminate\Database\Eloquent\Model;

/**
 * #140: row actions with a record-dependent `url(Closure)` or
 * `disabled(Closure)` must be resolved PER RECORD. Before the fix the
 * row actions were serialised once with a `null` record, so a closure
 * URL produced a single broken value shared by every row (no `{id}`
 * token to substitute) and a closure-disabled flag was evaluated once.
 *
 * The server now emits, per record under `arqel.actionOverrides`, a
 * map of `{actionName: {url?, disabled?}}` resolved against the real
 * row, so each row links to its own URL and carries its own disabled
 * state.
 *
 * Duck-typed action objects mimic `Arqel\Actions\Action` so we don't
 * pull `arqel-dev/actions` into `arqel-dev/core` (the dep graph goes the
 * other way). The fake replicates `Action::toArray($user, $record,
 * $resource)` + `hasRecordDependentUrl()` / `hasRecordDependentDisabled()`.
 *
 * The host environment does not ship with `pdo_sqlite`, so we drive the
 * private `serializeRecord` serialiser via Reflection instead of going
 * through `buildIndexData` (which would need a paginator → a DB).
 */
final class FakeClosureUrlAction
{
    /**
     * @param (Closure(mixed): string)|string|null $url
     * @param (Closure(mixed): bool)|null $disabled
     */
    public function __construct(
        public string $name,
        public Closure|string|null $url = null,
        public ?Closure $disabled = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleFor(mixed $record = null): bool
    {
        return true;
    }

    public function canBeExecutedBy(mixed $user = null, mixed $record = null): bool
    {
        return true;
    }

    public function hasRecordDependentUrl(): bool
    {
        return $this->url instanceof Closure;
    }

    public function hasRecordDependentDisabled(): bool
    {
        return $this->disabled instanceof Closure;
    }

    public function resolveUrl(mixed $record = null): ?string
    {
        if ($this->url instanceof Closure) {
            $resolved = ($this->url)($record);

            return is_string($resolved) ? $resolved : null;
        }

        return $this->url;
    }

    public function isDisabledFor(mixed $record = null): bool
    {
        if ($this->disabled === null) {
            return false;
        }

        return (bool) ($this->disabled)($record);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $user = null, mixed $record = null, ?object $resource = null): array
    {
        return array_filter([
            'name' => $this->name,
            'url' => $this->resolveUrl($record),
            'disabled' => $this->isDisabledFor($record) ?: null,
        ], fn ($v) => $v !== null);
    }
}

/**
 * @param array<int, mixed> $rowActions
 *
 * @return array<string, mixed>
 */
function serializeRecordFor(mixed $record, array $rowActions): array
{
    $builder = app(InertiaDataBuilder::class);
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('serializeRecord');
    $method->setAccessible(true);

    $resource = new PostResource;

    /** @var array<string, mixed> $payload */
    $payload = $method->invoke($builder, $record, $resource, $rowActions, null);

    return $payload;
}

it('resolves a closure-url row action to a distinct URL per record', function (): void {
    $first = new Stub(['name' => 'first']);
    $first->setAttribute('id', 1);
    $second = new Stub(['name' => 'second']);
    $second->setAttribute('id', 2);

    $action = new FakeClosureUrlAction(
        name: 'view',
        url: fn (Model $r): string => "/posts/{$r->getKey()}",
    );

    $firstPayload = serializeRecordFor($first, [$action]);
    $secondPayload = serializeRecordFor($second, [$action]);

    // Each row carries its own resolved URL — NOT a single shared one.
    expect($firstPayload['arqel']['actionOverrides']['view']['url'])->toBe('/posts/1')
        ->and($secondPayload['arqel']['actionOverrides']['view']['url'])->toBe('/posts/2');
});

it('resolves a closure-disabled row action per record', function (): void {
    $first = new Stub(['name' => 'first']);
    $first->setAttribute('id', 1);
    $second = new Stub(['name' => 'second']);
    $second->setAttribute('id', 2);

    $action = new FakeClosureUrlAction(
        name: 'edit',
        disabled: fn (Model $r): bool => $r->getKey() === 1,
    );

    $firstPayload = serializeRecordFor($first, [$action]);
    $secondPayload = serializeRecordFor($second, [$action]);

    // Record 1 disabled, record 2 not.
    expect($firstPayload['arqel']['actionOverrides']['edit']['disabled'])->toBeTrue()
        ->and($secondPayload['arqel']['actionOverrides'] ?? [])->not->toHaveKey('edit');
});

it('omits overrides for actions without record-dependent url or disabled', function (): void {
    $record = new Stub(['name' => 'first']);
    $record->setAttribute('id', 1);

    // A stock action (no closure url, no closure disabled) must not bloat
    // the per-row payload — the table-level definition + {id} template
    // path still handles it.
    $action = new FakeClosureUrlAction(name: 'view');

    $payload = serializeRecordFor($record, [$action]);

    expect($payload['arqel']['actionOverrides'] ?? [])->toBe([])
        ->and($payload['arqel']['actions'])->toBe(['view']);
});

it('still emits the visible action names list alongside the overrides', function (): void {
    $record = new Stub(['name' => 'first']);
    $record->setAttribute('id', 7);

    $action = new FakeClosureUrlAction(
        name: 'view',
        url: fn (Model $r): string => "/posts/{$r->getKey()}",
    );

    $payload = serializeRecordFor($record, [$action]);

    expect($payload['arqel']['actions'])->toBe(['view'])
        ->and($payload['arqel']['actionOverrides']['view']['url'])->toBe('/posts/7');
});
