<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Author;
use App\Models\Post;
use Arqel\Actions\Action;
use Arqel\Actions\Actions;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\RowAction;
use Arqel\Core\Resources\Resource;
use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\SelectField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;
use Arqel\Form\Layout\Grid;
use Arqel\Form\Layout\Group;
use Arqel\Form\Layout\Tab;
use Arqel\Form\Layout\Tabs;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\BooleanColumn;
use Arqel\Table\Columns\ComputedColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\RelationshipColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TernaryFilter;
use Arqel\Table\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Widest-surface showcase Resource: rich-text body, key/value meta,
 * a belongsTo author select, status badges, boolean toggle and a CSV
 * export bulk action. Primary probe target for the dogfood loop.
 */
final class PostResource extends Resource
{
    use BroadcastsResourceUpdates;

    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    /** @var class-string<Model> */
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
            ->columns(1)
            ->model(Post::class)
            ->schema([
                Tabs::make()
                    ->defaultTab('content')
                    ->tabs([
                        Tab::make('content', 'Content')
                            ->schema([
                                (new TextField('title'))
                                    ->required()
                                    ->columnSpan('full'),
                                Field::slug('slug')
                                    ->fromField('title')
                                    ->columnSpan('full'),
                                Field::richText('body')
                                    ->columnSpan('full'),
                            ]),
                        Tab::make('meta', 'Meta')
                            ->schema([
                                Grid::make()
                                    ->columns(['sm' => 1, 'md' => 2])
                                    ->schema([
                                        Field::select('author_id')
                                            ->options(self::authorOptions())
                                            ->required(),
                                        Field::select('status')
                                            ->options(self::STATUS_OPTIONS),
                                        (new BooleanField('featured'))
                                            ->inline(),
                                        (new DateTimeField('published_at')),
                                    ]),
                                Group::make()
                                    // visibleIf is invoked with a null record at
                                    // serialization (Component::isVisibleFor(null)),
                                    // so the predicate MUST be null-safe.
                                    ->visibleIf(fn ($record) => $record?->status === 'published')
                                    ->schema([
                                        Field::keyValue('meta')
                                            ->columnSpan('full'),
                                    ]),
                            ]),
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
                RelationshipColumn::make('author')->display('name')->label('Author'),
                ComputedColumn::make('word_count')->label('Words')
                    // getStateUsing is invoked with a null record at
                    // serialization (Column::getState(null)), so the
                    // closure MUST be null-safe.
                    ->getStateUsing(fn ($record) => str_word_count(strip_tags((string) ($record?->body ?? '')))),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
                TernaryFilter::make('featured'),
            ])
            ->defaultSort('published_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([
                Actions::edit(),
                Actions::delete(),
                RowAction::make('publish')
                    ->icon('check')
                    ->color(Action::COLOR_SUCCESS)
                    ->requiresConfirmation()
                    ->successNotification('Post published')
                    ->disabled(fn ($record): bool => $record?->status === 'published')
                    ->action(fn ($record) => $record->update(['status' => 'published'])),
                RowAction::make('change_status')
                    ->icon('refresh-cw')
                    ->form([
                        (new SelectField('status'))
                            ->options(self::STATUS_OPTIONS)
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->update(['status' => $data['status']])),
            ])
            ->bulkActions([
                Actions::deleteBulk(),
                ExportAction::make('export')->format(ExportFormat::CSV),
                BulkAction::make('archive')
                    ->icon('archive')
                    ->chunkSize(50)
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'archived'])),
            ]);
    }
}
