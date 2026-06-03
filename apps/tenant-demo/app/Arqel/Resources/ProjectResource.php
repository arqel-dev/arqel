<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Project;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Table;

/**
 * Tenant-scoped Resource. Because `App\Models\Project` uses
 * `BelongsToTenant`, every query the table runs is automatically
 * filtered to the current tenant — switching tenant changes the
 * list without any code here being tenant-aware.
 */
final class ProjectResource extends Resource
{
    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'planning' => 'Planning',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
    ];

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Project::class;

    public static ?string $slug = 'projects';

    public static ?string $label = 'Project';

    public static ?string $pluralLabel = 'Projects';

    public static ?string $navigationIcon = 'folder';

    public static ?string $navigationGroup = 'Workspace';

    public static ?int $navigationSort = 10;

    public static ?string $recordTitleAttribute = 'name';

    /**
     * Flat fields fallback (used when no form() is detected). Kept in
     * sync with form() so validation rules resolve identically.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('name'))->required(),
            Field::select('status')->options(self::STATUS_OPTIONS),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Project::class)
            ->schema([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        (new TextField('name'))
                            ->required()
                            ->columnSpan('full'),
                        Field::select('status')
                            ->options(self::STATUS_OPTIONS),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                BadgeColumn::make('status')->colors([
                    'planning' => 'gray',
                    'active' => 'green',
                    'on_hold' => 'yellow',
                    'completed' => 'blue',
                ]),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
            ])
            ->defaultSort('name')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
