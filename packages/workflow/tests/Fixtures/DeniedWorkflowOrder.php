<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture cuja única transition declarada (`DeniedTransition`) cobre
 * `Pending -> Paid` mas cujo `authorizeFor()` sempre nega. Exercita o
 * enforcement de autorização server-side de `transitionTo()`.
 *
 * @property string|null $order_state
 */
final class DeniedWorkflowOrder extends Model
{
    use HasWorkflow;

    protected $table = 'workflow_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                PendingState::class => ['label' => 'Pending'],
                PaidState::class => ['label' => 'Paid'],
            ])
            ->transitions([
                DeniedTransition::class,
            ]);
    }
}
