<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Author;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\EmailField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

final class AuthorResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Author::class;

    public static ?string $slug = 'authors';

    public static ?string $label = 'Author';

    public static ?string $pluralLabel = 'Authors';

    public static ?string $navigationIcon = 'user-pen';

    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 20;

    public static ?string $recordTitleAttribute = 'name';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('name'))->required(),
            new EmailField('email'),
            new TextareaField('bio'),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Author::class)
            ->schema([
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        (new TextField('name'))
                            ->required(),
                        new EmailField('email'),
                        (new TextareaField('bio'))
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->searchable(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
