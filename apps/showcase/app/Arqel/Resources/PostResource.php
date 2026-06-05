<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Author;
use App\Models\Post;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
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

/**
 * Widest-surface showcase Resource: rich-text body, key/value meta,
 * a belongsTo author select, status badges, boolean toggle and a CSV
 * export bulk action. Primary probe target for the dogfood loop.
 */
final class PostResource extends Resource
{
    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

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
     * Authors keyed by id for the belongsTo select.
     *
     * @return array<int, string>
     */
    private static function authorOptions(): array
    {
        /** @var array<int, string> $options */
        $options = Author::query()->pluck('name', 'id')->all();

        return $options;
    }

    /**
     * Flat fields fallback, kept in sync with form() so validation
     * rules resolve identically to the rendered form.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('title'))->required(),
            Field::slug('slug')->fromField('title'),
            Field::richText('body'),
            Field::select('author_id')->options(self::authorOptions()),
            Field::select('status')->options(self::STATUS_OPTIONS),
            new BooleanField('featured'),
            new DateTimeField('published_at'),
            Field::keyValue('meta'),
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
                        Field::richText('body')
                            ->columnSpan('full'),
                        Field::select('author_id')
                            ->options(self::authorOptions())
                            ->required(),
                    ]),
                Section::make('Meta')
                    ->columns(2)
                    ->schema([
                        Field::select('status')
                            ->options(self::STATUS_OPTIONS),
                        (new BooleanField('featured'))
                            ->inline(),
                        (new DateTimeField('published_at'))
                            ->columnSpan('full'),
                        Field::keyValue('meta')
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
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
                TernaryFilter::make('featured'),
            ])
            ->defaultSort('published_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([
                Actions::deleteBulk(),
                ExportAction::make('export')->format(ExportFormat::CSV),
            ]);
    }
}
