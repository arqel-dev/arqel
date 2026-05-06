<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\HiddenField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\BooleanColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TernaryFilter;
use Arqel\Table\Table;

final class PostResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Post::class;

    public static ?string $slug = 'posts';

    public static ?string $label = 'Post';

    public static ?string $pluralLabel = 'Posts';

    public static ?string $navigationIcon = 'file-text';

    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 10;

    public static ?string $recordTitleAttribute = 'title';

    /**
     * Flat fields fallback (used when no form() is detected by InertiaDataBuilder).
     * The rich form() below takes precedence via duck-typing.
     *
     * @return array<int, mixed>
     */
    /**
     * Flat fields fallback (used when no form() is detected by InertiaDataBuilder).
     * The rich form() below takes precedence via duck-typing.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('title'))->required(),
            Field::slug('slug'),
            new TextareaField('body'),
            Field::select('status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
                'archived' => 'Archived',
            ]),
            new BooleanField('featured'),
            new DateTimeField('published_at'),
            (new HiddenField('user_id'))->default(fn () => auth()->id()),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Post::class)
            ->schema([
                Section::make('Content')
                    ->columns(2)
                    ->schema([
                        (new TextField('title'))
                            ->required()
                            ->columnSpan('full'),
                        Field::slug('slug')
                            ->fromField('title')
                            ->columnSpan('full'),
                        (new TextareaField('body'))
                            ->columnSpan('full'),
                        (new HiddenField('user_id'))
                            ->default(fn () => auth()->id()),
                    ]),
                Section::make('Meta')
                    ->columns(2)
                    ->schema([
                        Field::select('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ]),
                        (new BooleanField('featured'))
                            ->inline(),
                        (new DateTimeField('published_at'))
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('title')->sortable()->searchable()->limit(80),
                BadgeColumn::make('status')->colors([
                    'draft' => 'gray',
                    'published' => 'green',
                    'archived' => 'yellow',
                ]),
                BooleanColumn::make('featured'),
                DateColumn::make('published_at')->sortable()->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
                TernaryFilter::make('featured'),
            ])
            ->defaultSort('published_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
