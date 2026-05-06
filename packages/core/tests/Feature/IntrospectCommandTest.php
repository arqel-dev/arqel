<?php

declare(strict_types=1);

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();
});

/**
 * Decode the JSON line emitted by `arqel:introspect`.
 *
 * @return array<string, mixed>
 */
function arqelIntrospectDecode(string $output): array
{
    $lines = array_values(array_filter(
        preg_split("/\r?\n/", trim($output)) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));

    $jsonLine = end($lines);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $jsonLine, true);

    return $decoded;
}

it('registers the arqel:introspect command', function (): void {
    expect(array_keys(Artisan::all()))->toContain('arqel:introspect');
});

it('emits a stable JSON shape with empty registries', function (): void {
    Artisan::call('arqel:introspect', ['--json' => true]);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded)->toHaveKeys(['version', 'scope', 'panels', 'resources', 'fields']);
    expect($decoded['scope'])->toBe('all');
    expect($decoded['panels'])->toBe([]);
    expect($decoded['resources'])->toBe([]);
    // Fields list reflects whatever the FieldFactory currently has
    // registered in the test environment — assert shape, not size.
    expect($decoded['fields'])->toBeArray();
});

it('serialises a panel with its id, path, and label', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->path('/admin')->brand('My Admin');

    Artisan::call('arqel:introspect', ['--json' => true]);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded['panels'])->toHaveCount(1);
    /** @var list<array{id: string, path: string, label: string}> $panelsOut */
    $panelsOut = $decoded['panels'];
    expect($panelsOut[0])->toMatchArray([
        'id' => 'admin',
        'path' => '/admin',
        'label' => 'My Admin',
    ]);
});

it('serialises a registered Resource with model, slug, labels, fields and policies', function (): void {
    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->register(UserResource::class);

    Artisan::call('arqel:introspect', ['--json' => true]);
    $decoded = arqelIntrospectDecode(Artisan::output());

    /** @var list<array<string, mixed>> $list */
    $list = $decoded['resources'];
    expect($list)->toHaveCount(1);

    $entry = $list[0];
    expect($entry['class'])->toBe(UserResource::class);
    expect($entry['model'])->toBe(UserResource::$model);
    expect($entry['label'])->toBeString();
    expect($entry['pluralLabel'])->toBeString();
    expect($entry['slug'])->toBeString();
    expect($entry['fields'])->toBe([]);
    expect($entry['policies'])->toBeArray();
});

it('serialises Field-like instances returned from a Resource', function (): void {
    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->register(IntrospectResourceWithFields::class);

    Artisan::call('arqel:introspect', ['--json' => true]);
    $decoded = arqelIntrospectDecode(Artisan::output());

    /** @var list<array<string, mixed>> $list */
    $list = $decoded['resources'];

    $resource = collect($list)->firstWhere('class', IntrospectResourceWithFields::class);
    expect($resource)->not->toBeNull();
    /** @var array<string, mixed> $resource */
    expect($resource['fields'])->toBe([
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'subtitle', 'type' => 'textarea'],
    ]);
});

it('skips field entries that do not expose getName/getType', function (): void {
    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->register(IntrospectResourceWithBrokenFields::class);

    Artisan::call('arqel:introspect', ['--json' => true]);
    $decoded = arqelIntrospectDecode(Artisan::output());

    /** @var list<array<string, mixed>> $list */
    $list = $decoded['resources'];
    $resource = collect($list)->firstWhere('class', IntrospectResourceWithBrokenFields::class);
    expect($resource)->not->toBeNull();
    /** @var array<string, mixed> $resource */
    expect($resource['fields'])->toBe([]);
});

it('honours --scope=panels by emitting only the panels section', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->register(PostResource::class);

    Artisan::call('arqel:introspect', ['--json' => true, '--scope' => 'panels']);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded['scope'])->toBe('panels');
    expect($decoded['panels'])->not->toBe([]);
    expect($decoded['resources'])->toBe([]);
    expect($decoded['fields'])->toBe([]);
});

it('honours --scope=resources by emitting only the resources section', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->register(PostResource::class);

    Artisan::call('arqel:introspect', ['--json' => true, '--scope' => 'resources']);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded['scope'])->toBe('resources');
    expect($decoded['panels'])->toBe([]);
    expect($decoded['resources'])->not->toBe([]);
    expect($decoded['fields'])->toBe([]);
});

it('falls back to scope=all when given an unknown value', function (): void {
    Artisan::call('arqel:introspect', ['--json' => true, '--scope' => 'bogus']);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded['scope'])->toBe('all');
});

it('emits an empty fields list when arqel-dev/fields is not installed', function (): void {
    // The core test runtime does not autoload `arqel-dev/fields`, so
    // FieldFactory is unavailable. The command must degrade gracefully
    // and produce `[]` rather than throwing.
    Artisan::call('arqel:introspect', ['--json' => true, '--scope' => 'fields']);
    $decoded = arqelIntrospectDecode(Artisan::output());

    expect($decoded['fields'])->toBe([]);
});

it('exits 0 even when registries are empty', function (): void {
    expect(Artisan::call('arqel:introspect'))->toBe(0);
});

/**
 * Minimal Field-shaped stub that exposes `getName`/`getType`. The core
 * test environment does not autoload `arqel-dev/fields`, so we cannot
 * instantiate real Field types here — the introspect command duck-types
 * the field list and only requires those two methods.
 */
final class IntrospectFakeField
{
    public function __construct(private readonly string $name, private readonly string $type) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

final class IntrospectResourceWithFields extends Arqel\Core\Resources\Resource
{
    public static string $model = Arqel\Core\Tests\Fixtures\Models\Post::class;

    public function fields(): array
    {
        return [
            new IntrospectFakeField('title', 'text'),
            new IntrospectFakeField('subtitle', 'textarea'),
        ];
    }
}

final class IntrospectResourceWithBrokenFields extends Arqel\Core\Resources\Resource
{
    public static string $model = Arqel\Core\Tests\Fixtures\Models\Post::class;

    public function fields(): array
    {
        // None of these expose getName + getType, so the command should
        // skip them and emit an empty fields list.
        return [
            'not-a-field',
            42,
            new stdClass,
        ];
    }
}
