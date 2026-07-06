<?php

declare(strict_types=1);

namespace App\Arqel\Relations;

use Arqel\Core\Relations\RelationManager;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

/**
 * Author→Post is the strongest hasMany in the showcase (5 authors × 30 posts,
 * seeded), but this manager lives on `Post::comments()` (also hasMany, seeded
 * with 1-3 `Comment`s per post via `DatabaseSeeder`) so it can be dogfooded
 * from the Post edit page alongside the belongsToMany `CategoriesRelationManager`
 * below — one parent record, one hasMany tab and one belongsToMany tab.
 */
final class CommentsRelationManager extends RelationManager
{
    public static string $relationship = 'comments';

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('body')->limit(80),
                DateColumn::make('created_at')->sortable()->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            Field::textarea('body')->required(),
        ];
    }
}
