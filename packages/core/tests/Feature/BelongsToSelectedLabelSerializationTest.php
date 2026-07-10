<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Support\FieldSchemaSerializer;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Bug: editing a record with a `belongsTo` field showed the raw FK id
 * (e.g. "42") instead of the related record's title (e.g. "Ada
 * Lovelace") because `BelongsToInput.tsx` only resolves a label from
 * async search results, which are empty on initial load.
 *
 * Fix: `FieldSchemaSerializer` resolves the related record (when a
 * record + a non-null FK are available) and emits `props.selectedLabel`
 * using the same title mechanism as `FieldSearchController`
 * (`Resource::recordTitle()` of the field's `relatedResource`), so the
 * React input has an initial label before any search has run.
 *
 * `arqel-dev/core` does not depend on `arqel-dev/fields`, so this test
 * drives a duck-typed BelongsTo stub mirroring `BelongsToField`'s
 * contract: `getTypeSpecificProps()` carrying `relatedResource` +
 * `relationship`.
 */
final class SlAuthor extends Model
{
    protected $table = 'sl_authors';

    protected $guarded = [];

    public $timestamps = false;
}

final class SlPost extends Model
{
    protected $table = 'sl_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(SlAuthor::class, 'author_id');
    }
}

final class SlAuthorResource extends Resource
{
    public static string $model = SlAuthor::class;

    public static ?string $slug = 'sl-authors';

    public static ?string $recordTitleAttribute = 'name';

    public function fields(): array
    {
        return [];
    }
}

/**
 * Minimal stand-in for `Arqel\Fields\Types\BelongsToField` exposing
 * only the surface the serialiser duck-types against.
 */
final class StubBelongsToRelationField
{
    public function __construct(
        private readonly string $name = 'author_id',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'belongsTo';
    }

    public function getComponent(): string
    {
        return 'BelongsToInput';
    }

    /** @return array<string, mixed> */
    public function getTypeSpecificProps(): array
    {
        return [
            'relatedResource' => SlAuthorResource::class,
            'relationship' => 'author',
            'searchable' => true,
            'searchColumns' => [],
            'preload' => false,
        ];
    }
}

final class SlPostResource extends Resource
{
    public static string $model = SlPost::class;

    public static ?string $slug = 'sl-posts';

    public function fields(): array
    {
        return [new StubBelongsToRelationField];
    }
}

beforeEach(function (): void {
    Schema::create('sl_authors', function ($table): void {
        $table->increments('id');
        $table->string('name');
    });

    Schema::create('sl_posts', function ($table): void {
        $table->increments('id');
        $table->unsignedInteger('author_id')->nullable();
    });

    SlAuthor::query()->insert([
        ['id' => 1, 'name' => 'Ada Lovelace'],
    ]);
});

it('emits props.selectedLabel with the related record title when the FK is filled', function (): void {
    $record = new SlPost;
    $record->setRawAttributes(['id' => 5, 'author_id' => 1]);
    $record->exists = true;

    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubBelongsToRelationField], $record, null, $record);

    expect($serialized[0]['props']['selectedLabel'])->toBe('Ada Lovelace');
});

it('does not emit selectedLabel when there is no record', function (): void {
    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubBelongsToRelationField]);

    expect($serialized[0]['props'])->not->toHaveKey('selectedLabel');
});

it('does not emit selectedLabel when the FK is null', function (): void {
    $record = new SlPost;
    $record->setRawAttributes(['id' => 5, 'author_id' => null]);
    $record->exists = true;

    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubBelongsToRelationField], $record, null, $record);

    expect($serialized[0]['props'])->not->toHaveKey('selectedLabel');
});

it('does not crash and skips selectedLabel when the related record no longer exists', function (): void {
    $record = new SlPost;
    $record->setRawAttributes(['id' => 5, 'author_id' => 999]);
    $record->exists = true;

    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubBelongsToRelationField], $record, null, $record);

    expect($serialized[0]['props'])->not->toHaveKey('selectedLabel');
});

it('populates selectedLabel in the edit payload built by InertiaDataBuilder', function (): void {
    $record = new SlPost;
    $record->setRawAttributes(['id' => 5, 'author_id' => 1]);
    $record->exists = true;

    $payload = app(InertiaDataBuilder::class)->buildEditData(new SlPostResource, $record, new Request);

    expect($payload['fields'][0]['props']['selectedLabel'])->toBe('Ada Lovelace');
});

it('does not populate selectedLabel in the create payload (fresh model, no FK value)', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new SlPostResource, new Request);

    expect($payload['fields'][0]['props'])->not->toHaveKey('selectedLabel');
});
