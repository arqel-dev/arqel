<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture exercising the *spatie* path of `HasWorkflow::transitionTo()`: the
 * `order_state` attribute is cast to a {@see SpatieStyleState} object that owns
 * its own `transitionTo()` — exactly what the trait detects as the spatie path
 * (`is_object($value) && method_exists($value, 'transitionTo')`).
 *
 * The Arqel definition declares the spatie state under its FQCN (mirroring how
 * spatie keys metadata by `State::class`) so authorization resolves the
 * `transition-spatie-style-to-paid` ability for the deny-by-default check (#242).
 *
 * @property SpatieStyleState|string|null $order_state
 */
final class SpatieWorkflowOrder extends Model
{
    use HasWorkflow;

    protected $table = 'workflow_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                SpatieStyleState::class => ['label' => 'Pending'],
                PaidState::class => ['label' => 'Paid'],
            ])
            ->transitions([
                PendingToPaid::class,
            ]);
    }

    /**
     * Cast `order_state` to a {@see SpatieStyleState} object so `transitionTo()`
     * takes the spatie branch. Mirrors how a spatie state cast hydrates a State
     * instance from the persisted token.
     *
     * @return Attribute<SpatieStyleState|null, string|null>
     */
    protected function orderState(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?SpatieStyleState => is_string($value) && $value !== ''
                ? new SpatieStyleState($value)
                : null,
            set: static fn (mixed $value): ?string => $value instanceof SpatieStyleState
                ? $value->name
                : (is_string($value) ? $value : null),
        );
    }
}
