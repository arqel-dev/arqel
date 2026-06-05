<?php

declare(strict_types=1);

namespace App\Models;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    // Workflow metadata + transition history via `arqelWorkflow()`.
    use HasWorkflow;

    /** @var list<string> */
    protected $fillable = ['subject', 'status'];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('status')
            ->states([
                'open' => ['label' => 'Open', 'color' => 'blue', 'icon' => 'circle'],
                'in_progress' => ['label' => 'In Progress', 'color' => 'yellow', 'icon' => 'clock'],
                'resolved' => ['label' => 'Resolved', 'color' => 'green', 'icon' => 'check'],
            ])
            ->transitions([]);
    }
}
