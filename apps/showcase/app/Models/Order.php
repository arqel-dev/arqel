<?php

declare(strict_types=1);

namespace App\Models;

use App\Workflow\Transitions\AnyToCancelled;
use App\Workflow\Transitions\PaidToShipped;
use App\Workflow\Transitions\PendingToPaid;
use App\Workflow\Transitions\ShippedToDelivered;
use Arqel\Audit\Concerns\LogsActivity;
use Arqel\Tenant\Concerns\BelongsToTenant;
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    // Tenant scoping + auto-fill of tenant_id on create.
    use BelongsToTenant;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    // Workflow metadata + guarded transitions via `arqelWorkflow()`.
    use HasWorkflow;

    // Spatie activity log with Arqel defaults (logs the fillable attributes).
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'reference', 'customer_name', 'total', 'state'];

    /** @var array<string, string> */
    protected $casts = ['total' => 'decimal:2'];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('state')
            ->states([
                'pending' => ['label' => 'Pending', 'color' => 'gray', 'icon' => 'clock'],
                'paid' => ['label' => 'Paid', 'color' => 'blue', 'icon' => 'credit-card'],
                'shipped' => ['label' => 'Shipped', 'color' => 'yellow', 'icon' => 'truck'],
                'delivered' => ['label' => 'Delivered', 'color' => 'green', 'icon' => 'check'],
                'cancelled' => ['label' => 'Cancelled', 'color' => 'red', 'icon' => 'x'],
            ])
            ->transitions([
                PendingToPaid::class,
                PaidToShipped::class,
                ShippedToDelivered::class,
                AnyToCancelled::class,
            ]);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
