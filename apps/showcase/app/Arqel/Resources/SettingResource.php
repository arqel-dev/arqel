<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Setting;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Types\RepeaterField;
use Arqel\FieldsAdvanced\Types\TagsField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

/**
 * Advanced-fields probe: repeater (nested schema), tags, code and
 * markdown editors. Only `key` and `value` are persisted columns; the
 * tags/snippet/notes fields exist purely to exercise the advanced
 * field renderers in the dogfood loop (no backing columns).
 */
final class SettingResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Setting::class;

    public static ?string $slug = 'settings';

    public static ?string $label = 'Setting';

    public static ?string $pluralLabel = 'Settings';

    public static ?string $navigationIcon = 'settings';

    public static ?string $navigationGroup = 'System';

    public static ?int $navigationSort = 10;

    public static ?string $recordTitleAttribute = 'key';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('key'))->required(),
            RepeaterField::make('value')->schema([
                Field::text('label'),
                Field::text('content'),
            ]),
            TagsField::make('tags'),
            Field::code('snippet')->language('json'),
            Field::markdown('notes'),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(1)
            ->model(Setting::class)
            ->schema([
                Section::make('Setting')
                    ->columns(1)
                    ->schema([
                        (new TextField('key'))
                            ->required(),
                        RepeaterField::make('value')->schema([
                            Field::text('label'),
                            Field::text('content'),
                        ]),
                        TagsField::make('tags'),
                        Field::code('snippet')->language('json'),
                        Field::markdown('notes'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('key')->sortable()->searchable(),
            ])
            ->defaultSort('key')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
