<?php

declare(strict_types=1);

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Arqel\Core\Tests\Fixtures\Relations\StubRelationColumn;
use Arqel\Core\Tests\Fixtures\Relations\StubRelationTable;
use Illuminate\Support\Facades\Gate;

it('serializes slug, label, type, table schema and abilities', function (): void {
    $array = (new CommentsRelationManager)->toArray(new RelPost, null);

    expect($array['slug'])->toBe('comments')
        ->and($array['label'])->toBe('Comments')
        ->and($array['type'])->toBe('hasMany')
        ->and($array['table'])->toBeArray()
        ->and($array['fields'])->toBeArray()
        ->and($array['abilities'])->toHaveKeys(['create', 'update', 'delete', 'attach', 'detach']);
});

it('fails open on abilities when no policy is registered', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['create'])->toBeTrue()
        ->and($abilities['update'])->toBeTrue();
});

it('never grants attach/detach for a non-belongsToMany relation', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['attach'])->toBeFalse()
        ->and($abilities['detach'])->toBeFalse();
});

it('denies abilities when a closure gate (no Policy) rejects, matching ResourceController two-tier semantics', function (): void {
    Gate::define('delete', fn (): bool => false);

    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['delete'])->toBeFalse();
});

it('serializes table columns through the toArray() pipeline so each column carries a name (not an empty {})', function (): void {
    // Regression test for the E2E-diagnosed bug: RelationManager::toArray()
    // used to emit raw $table->toArray()['columns'], which for a real
    // Arqel\Table\Table (and this duck-typed stub) is a list of unserialized
    // Column OBJECTS. Those JSON-encode to `{}` — no `name`/`label` — which
    // crashes the React <DataTable> (col.name undefined -> "Columns require
    // an id when using an accessorFn"), blanking the relation panel and
    // hanging the E2E test waiting for `table tbody tr`.
    //
    // CommentsRelationManager::table() already declares
    // StubRelationColumn('body') + StubRelationColumn('secret', visible:
    // false); StubRelationColumn now exposes toArray() (added alongside this
    // fix) so this test can prove the schema is serialized per-column, not
    // just handed through the pipeline that runs on a bare-array stub.
    $manager = new CommentsRelationManager;

    $array = $manager->toArray(new RelPost, null);

    $columns = $array['table']['columns'];

    expect($columns)->toBeArray()->not->toBeEmpty();

    foreach ($columns as $column) {
        expect($column)->toBeArray()
            ->and($column)->toHaveKey('name')
            ->and($column['name'])->not->toBeNull()
            ->and($column['name'])->not->toBe('');
    }

    $names = array_column($columns, 'name');
    expect($names)->toContain('body')->toContain('secret');
});

it('falls back to an empty columns array for a manager whose table has no getColumns()', function (): void {
    // StubRelationTable's own toArray() (a raw ['columns' => []]) is never
    // reached by RelationManager::toArray() anymore — serializeTableSchema()
    // drives everything through getColumns()/getFilters()/etc. A table
    // declaring no columns still yields a well-formed (empty) list, not a
    // crash.
    $manager = new class extends RelationManager
    {
        public static string $relationship = 'comments';

        public function table(): mixed
        {
            return new StubRelationTable;
        }
    };

    $array = $manager->toArray(new RelPost, null);

    expect($array['table']['columns'])->toBe([]);
});
