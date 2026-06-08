<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\MediaAsset;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\ImageField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\ImageColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Image-upload showcase Resource. The form drives an ImageField on the
 * public disk and the index renders an ImageColumn thumbnail, exercising
 * the upload + storage-URL contract end to end.
 */
final class MediaResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = MediaAsset::class;

    public static ?string $slug = 'media-assets';

    public static ?string $label = 'Media';

    public static ?string $pluralLabel = 'Media';

    public static ?string $navigationIcon = 'image';

    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 30;

    public static ?string $recordTitleAttribute = 'title';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('title'))->required(),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(1)
            ->model(MediaAsset::class)
            ->schema([
                Section::make('Media')
                    ->schema([
                        (new TextField('title'))
                            ->required()
                            ->columnSpan('full'),
                        (new ImageField('file_path'))
                            ->disk('public')
                            ->directory('media')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                ImageColumn::make('file_path')->disk('public'),
                TextColumn::make('title')->sortable()->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
