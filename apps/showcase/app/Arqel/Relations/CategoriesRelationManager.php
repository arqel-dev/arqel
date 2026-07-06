<?php

declare(strict_types=1);

namespace App\Arqel\Relations;

use Arqel\Core\Relations\RelationManager;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

/**
 * `Post::categories()` is the showcase's only real belongsToMany (pivot table
 * `category_post`, seeded via `DatabaseSeeder` with 1-3 categories attached
 * per post). No pivot columns exist on that table, so `pivotFields()` stays
 * at the base class's empty default — attach() accepts no extra pivot data.
 */
final class CategoriesRelationManager extends RelationManager
{
    public static string $relationship = 'categories';

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
            ])
            ->defaultSort('name');
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            Field::text('name')->required(),
        ];
    }
}
