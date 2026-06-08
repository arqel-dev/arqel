<?php

declare(strict_types=1);

use Arqel\Fields\Types\SelectField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * #204: `SelectField::optionsRelationship('category', 'name')` must
 * resolve the relation into a `{key: label}` option map against the
 * owning model, instead of falling through to an empty list. The
 * resolution lives in `resolveOptionsForOwner()`, which the core
 * serialiser calls with the Resource model.
 */
final class SelectOptionsCategory extends Model
{
    protected $table = 'select_opt_categories';

    protected $guarded = [];

    public $timestamps = false;
}

final class SelectOptionsPost extends Model
{
    protected $table = 'select_opt_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function category(): BelongsTo
    {
        return $this->belongsTo(SelectOptionsCategory::class, 'category_id');
    }
}

beforeEach(function (): void {
    Schema::create('select_opt_categories', function ($table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('active')->default(true);
    });

    Schema::create('select_opt_posts', function ($table): void {
        $table->increments('id');
        $table->unsignedInteger('category_id')->nullable();
    });

    SelectOptionsCategory::query()->insert([
        ['id' => 1, 'name' => 'News', 'active' => true],
        ['id' => 2, 'name' => 'Tutorials', 'active' => true],
        ['id' => 3, 'name' => 'Archived', 'active' => false],
    ]);
});

it('resolves relationship options into an id => label map against the owner', function (): void {
    $field = (new SelectField('category_id'))->optionsRelationship('category', 'name');

    $options = $field->resolveOptionsForOwner(new SelectOptionsPost);

    expect($options)->toBe([
        1 => 'News',
        2 => 'Tutorials',
        3 => 'Archived',
    ]);
});

it('applies the optionsRelationQuery constraint when resolving', function (): void {
    $field = (new SelectField('category_id'))->optionsRelationship(
        'category',
        'name',
        fn ($query) => $query->where('active', true),
    );

    $options = $field->resolveOptionsForOwner(new SelectOptionsPost);

    expect($options)->toBe([
        1 => 'News',
        2 => 'Tutorials',
    ]);
});

it('returns an empty array when the relation does not exist on the owner', function (): void {
    $field = (new SelectField('category_id'))->optionsRelationship('missing', 'name');

    expect($field->resolveOptionsForOwner(new SelectOptionsPost))->toBe([]);
});

it('returns an empty array for static/closure selects (no relation configured)', function (): void {
    $static = (new SelectField('status'))->options(['draft' => 'Draft']);
    $closure = (new SelectField('status'))->options(fn () => [1 => 'A']);

    expect($static->resolveOptionsForOwner(new SelectOptionsPost))->toBe([])
        ->and($closure->resolveOptionsForOwner(new SelectOptionsPost))->toBe([]);
});
