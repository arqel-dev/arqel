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
 * Sortable relationship columns require a JOIN; that wiring lives
 * in `TableQueryBuilder` (TABLE-005). The column itself just
 * carries the metadata.
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
