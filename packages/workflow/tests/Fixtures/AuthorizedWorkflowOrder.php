<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture cuja única transition declarada (`AuthorizedTransition`) cobre
 * `Pending -> Paid` e cujo `authorizeFor()` sempre permite. Exercita o
 * caminho legal + autorizado de `transitionTo()` sem depender de Gates.
 *
 * @property string|null $order_state
 */
final class AuthorizedWorkflowOrder extends Model
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
                AuthorizedTransition::class,
            ]);
    }
}
