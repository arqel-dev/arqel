<?php

declare(strict_types=1);

namespace Arqel\Core\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Base class a consumer app extends to manage a parent record's Eloquent
 * relation from the parent's edit page.
 *
 * The child sets `$relationship`, declares `table()` (and optionally
 * `form()`), and the relation type (hasMany/morphMany/belongsToMany) is
 * detected at runtime from the parent's relation instance. MorphTo and
 * HasManyThrough are intentionally out of scope for 0.18.
 *
 * `table()`/`form()` return `mixed` (a Table-/Form-shaped object) rather
 * than concrete `Arqel\Table\Table`/`Arqel\Form\Form` types: `arqel-dev/core`
 * deliberately does not depend on `arqel-dev/table`/`arqel-dev/form` (they
 * depend on core — a hard type-hint would be a circular dependency). This
 * mirrors `Resource::table()`/`form()`, which are `mixed` for the same
 * documented reason. Consumers/serializers duck-type via `->toArray()`.
 */
abstract class RelationManager
{
    /** @var string Eloquent relation method name on the parent model. */
    public static string $relationship;

    abstract public function table(): mixed;

    /**
     * Field list for create/edit — the validation source, exactly like
     * `Resource::fields()`. The RelationController extracts rules from these
     * via the same string-referenced FieldRulesExtractor the ResourceController
     * uses, so `core` stays free of a hard dependency on arqel-dev/form.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return null;
    }

    /** @return class-string|null */
    public function relatedResource(): ?string
    {
        return null;
    }

    public function slug(): string
    {
        return Str::snake(static::$relationship);
    }

    /**
     * Detect the supported relation type from the parent's relation instance.
     *
     * @return 'hasMany'|'morphMany'|'belongsToMany'
     */
    public function relationType(Model $parent): string
    {
        $relation = $parent->{static::$relationship}();

        return match (true) {
            $relation instanceof MorphMany => 'morphMany',
            $relation instanceof BelongsToMany => 'belongsToMany',
            $relation instanceof HasMany => 'hasMany',
            default => throw new InvalidArgumentException(sprintf(
                'Relation [%s] on [%s] is of an unsupported type for a RelationManager (only hasMany/morphMany/belongsToMany).',
                static::$relationship,
                $parent::class,
            )),
        };
    }

    public function supportsAttach(Model $parent): bool
    {
        return $this->relationType($parent) === 'belongsToMany';
    }
}
