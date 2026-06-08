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
 * #204: a relationship-backed select must arrive at the client with
 * `props.options` populated. The serialiser resolves the relation
 * against the owner model (record on edit/show, fresh instance on
 * create) and injects the `{key: label}` map.
 *
 * `arqel-dev/core` does not depend on `arqel-dev/fields`, so this test
 * drives a duck-typed select stub that mirrors the SelectField
 * contract the serialiser relies on: `getOptionsRelation()` and
 * `resolveOptionsForOwner(Model)`.
 */
final class RelOptCategory extends Model
{
    protected $table = 'rel_opt_categories';

    protected $guarded = [];

    public $timestamps = false;
}

final class RelOptPost extends Model
{
    protected $table = 'rel_opt_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function category(): BelongsTo
    {
        return $this->belongsTo(RelOptCategory::class, 'category_id');
    }
}

/**
 * Minimal stand-in for `Arqel\Fields\Types\SelectField` exposing only
 * the surface the serialiser duck-types against.
 */
final class StubRelationSelectField
{
    public function __construct(
        private readonly string $name = 'category_id',
        private readonly ?string $relation = 'category',
        private readonly string $display = 'name',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'select';
    }

    public function getComponent(): string
    {
        return 'SelectInput';
    }

    public function getOptionsRelation(): ?string
    {
        return $this->relation;
    }

    /** @return array<string, mixed> */
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => [],
            'optionsRelation' => $this->relation,
        ];
    }

    /** @return array<int|string, string> */
    public function resolveOptionsForOwner(Model $owner): array
    {
        if ($this->relation === null || ! method_exists($owner, $this->relation)) {
            return [];
        }

        $related = $owner->{$this->relation}()->getRelated();

        /** @var array<int|string, string> $options */
        $options = $related->newQuery()
            ->pluck($this->display, $related->getKeyName())
            ->map(static fn (mixed $label): string => (string) $label)
            ->all();

        return $options;
    }
}

final class RelOptPostResource extends Resource
{
    public static string $model = RelOptPost::class;

    public static ?string $slug = 'rel-opt-posts';

    public function fields(): array
    {
        return [new StubRelationSelectField];
    }
}

beforeEach(function (): void {
    Schema::create('rel_opt_categories', function ($table): void {
        $table->increments('id');
        $table->string('name');
    });

    Schema::create('rel_opt_posts', function ($table): void {
        $table->increments('id');
        $table->unsignedInteger('category_id')->nullable();
    });

    RelOptCategory::query()->insert([
        ['id' => 1, 'name' => 'News'],
        ['id' => 2, 'name' => 'Tutorials'],
    ]);
});

it('injects relationship options into props.options when serialising a select', function (): void {
    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubRelationSelectField], null, null, new RelOptPost);

    expect($serialized[0]['props']['options'])->toBe([
        1 => 'News',
        2 => 'Tutorials',
    ]);
});

it('leaves props.options empty when no owner model is available', function (): void {
    $serializer = new FieldSchemaSerializer;

    $serialized = $serializer->serialize([new StubRelationSelectField]);

    expect($serialized[0]['props']['options'])->toBe([]);
});

it('populates relationship options in the create payload with no record', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new RelOptPostResource, new Request);

    expect($payload['record'])->toBeNull()
        ->and($payload['fields'][0]['props']['options'])->toBe([
            1 => 'News',
            2 => 'Tutorials',
        ]);
});

it('populates relationship options in the edit payload from the record', function (): void {
    $record = new RelOptPost;
    $record->setRawAttributes(['id' => 5, 'category_id' => 1]);
    $record->exists = true;

    $payload = app(InertiaDataBuilder::class)->buildEditData(new RelOptPostResource, $record, new Request);

    expect($payload['fields'][0]['props']['options'])->toBe([
        1 => 'News',
        2 => 'Tutorials',
    ]);
});
