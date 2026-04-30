<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture model exercising `HasWorkflow` without spatie/laravel-model-states.
 * The `order_state` attribute holds the FQCN of the current state stub.
 *
 * @property string|null $order_state
 */
final class WorkflowOrder extends Model
{
    use HasWorkflow;

    protected $table = 'workflow_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                PendingState::class => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'clock'],
                PaidState::class => ['label' => 'Paid', 'color' => 'info', 'icon' => 'credit-card'],
                ShippedState::class => ['label' => 'Shipped', 'color' => 'primary', 'icon' => 'truck'],
                CancelledState::class => ['label' => 'Cancelled', 'color' => 'destructive', 'icon' => 'x-circle'],
            ])
            ->transitions([
                PendingToPaid::class,
                PaidToShipped::class,
                AnyToCancelled::class,
            ]);
    }
}
