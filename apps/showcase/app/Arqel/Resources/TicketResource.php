<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Ticket;
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
 * The Ticket model defines an `arqelWorkflow()` over `status`; this
 * Resource only renders the current status. Deep workflow UI (guarded
 * transitions, history) is out of scope here.
 */
final class TicketResource extends Resource
{
    /** @var array<string, string> */
    private const STATUS_OPTIONS = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
    ];

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Ticket::class;

    public static ?string $slug = 'tickets';

    public static ?string $label = 'Ticket';

    public static ?string $pluralLabel = 'Tickets';

    public static ?string $navigationIcon = 'life-buoy';

    public static ?string $navigationGroup = 'Support';

    public static ?int $navigationSort = 10;

    public static ?string $recordTitleAttribute = 'subject';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('subject'))->required(),
            Field::select('status')->options(self::STATUS_OPTIONS),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Ticket::class)
            ->schema([
                Section::make('Ticket')
                    ->columns(2)
                    ->schema([
                        (new TextField('subject'))
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
                TextColumn::make('subject')->searchable(),
                BadgeColumn::make('status')->colors([
                    'open' => 'blue',
                    'in_progress' => 'yellow',
                    'resolved' => 'green',
                ]),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
