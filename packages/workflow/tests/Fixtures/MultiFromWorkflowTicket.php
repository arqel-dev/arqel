<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture model whose workflow uses plain string state keys and declares a
 * single transition with a multi-state `from()` list. Used to reproduce the
 * #154 duplicate-entry bug: when the current state is in the multi-from list,
 * `StateTransitionField::resolveAvailableTransitions()` must emit one entry.
 *
 * @property string|null $ticket_state
 */
final class MultiFromWorkflowTicket extends Model
{
    use HasWorkflow;

    protected $table = 'workflow_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('ticket_state')
            ->states([
                'open' => ['label' => 'Open', 'color' => 'warning', 'icon' => 'inbox'],
                'in_progress' => ['label' => 'In Progress', 'color' => 'info', 'icon' => 'loader'],
                'resolved' => ['label' => 'Resolved', 'color' => 'primary', 'icon' => 'check'],
            ])
            ->transitions([
                MultiFromToResolved::class,
            ]);
    }
}
