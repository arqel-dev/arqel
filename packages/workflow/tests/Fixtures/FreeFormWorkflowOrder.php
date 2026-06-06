<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture sem transitions declaradas (`transitions([])`) — espelha o
 * `Ticket` da showcase. O contrato é free-form: `transitionTo()` não impõe
 * eligibility nem autorização, qualquer state é aceito.
 *
 * @property string|null $order_state
 */
final class FreeFormWorkflowOrder extends Model
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
            ->transitions([]);
    }
}
