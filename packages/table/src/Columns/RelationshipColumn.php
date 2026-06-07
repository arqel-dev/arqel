<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Illuminate\Database\Eloquent\Model;

/**
 * Cell whose value comes from a relation's column.
 *
 * `display('name')` selects the column from the related model.
 * `getState()` walks the relation by its name (declared via the
 * column's `name`) and returns the configured display attribute.
 *
 * Sorting and searching by a relationship column require a JOIN on
 * the related table; that wiring is not implemented yet (deferred to
 * TABLE-005). Until then `TableQueryBuilder` excludes relationship
 * columns from the sort/search whitelists, so a `->sortable()` or
 * `->searchable()` declaration degrades to a no-op rather than
 * emitting an "Unknown column" SQL error.
 */
final class RelationshipColumn extends TextColumn
{
    protected string $type = 'relationship';

    protected string $displayAttribute = 'name';

    public function display(string $attribute): static
    {
        $this->displayAttribute = $attribute;

        return $this;
    }

    public function getDisplayAttribute(): string
    {
        return $this->displayAttribute;
    }

    public function getState(?Model $record): mixed
    {
        if ($record === null) {
            return null;
        }

        $related = $record->getAttribute($this->name);
        if ($related instanceof Model) {
            return $related->getAttribute($this->displayAttribute);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            ...parent::getTypeSpecificProps(),
            'displayAttribute' => $this->displayAttribute,
        ];
    }
}
