<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Order;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\NumberColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Table;
use Arqel\Workflow\Fields\StateTransitionField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow + soft-delete showcase Resource. The `state` column drives a
 * BadgeColumn whose palette mirrors the WorkflowDefinition, and the form
 * embeds a StateTransitionField so the available guarded transitions
 * render inline. Order uses SoftDeletes, so the index relies on the
 * Eloquent default scope to hide trashed rows.
 */
final class OrderResource extends Resource
{
    /** @var array<string, string> */
    private const STATE_OPTIONS = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    /** @var array<string, string> */
    private const TRASHED_OPTIONS = [
        'with' => 'With trashed',
        'only' => 'Only trashed',
    ];

    /** @var class-string<Model> */
    public static string $model = Order::class;

    public static ?string $slug = 'orders';

    public static ?string $label = 'Order';

    public static ?string $pluralLabel = 'Orders';

    public static ?string $navigationIcon = 'shopping-cart';

    public static ?string $navigationGroup = 'Commerce';

    public static ?int $navigationSort = 10;

    public static ?string $recordTitleAttribute = 'reference';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('reference'))->required(),
            (new TextField('customer_name'))->required(),
            new TextField('total'),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(1)
            ->model(Order::class)
            ->schema([
                Section::make('Order')
                    ->columns(2)
                    ->schema([
                        (new TextField('reference'))->required(),
                        (new TextField('customer_name'))->required(),
                        (new TextField('total')),
                        StateTransitionField::make('state')
                            ->showHistory()
                            ->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('reference')->sortable()->searchable(),
                TextColumn::make('customer_name')->searchable(),
                NumberColumn::make('total')->money('USD'),
                BadgeColumn::make('state')->colors([
                    'pending' => 'gray',
                    'paid' => 'blue',
                    'shipped' => 'yellow',
                    'delivered' => 'green',
                    'cancelled' => 'red',
                ]),
            ])
            ->filters([
                SelectFilter::make('state')->options(self::STATE_OPTIONS),
                // The framework ships no trashed/soft-delete filter primitive
                // (no TrashedFilter; nothing in packages/table calls
                // withTrashed/onlyTrashed — Round-22 candidate #6). Hand-rolled
                // via SelectFilter::apply(): 'with' includes trashed rows,
                // 'only' shows just the 5 soft-deleted orders; default (null)
                // leaves the SoftDeletes global scope intact so trashed rows
                // stay hidden.
                SelectFilter::make('trashed')
                    ->label('Trashed')
                    ->options(self::TRASHED_OPTIONS)
                    ->apply(static function (Builder $query, mixed $value): Builder {
                        if ($value === 'only') {
                            /** @var Builder<Order> $query */
                            return $query->onlyTrashed();
                        }

                        if ($value === 'with') {
                            /** @var Builder<Order> $query */
                            return $query->withTrashed();
                        }

                        return $query;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
