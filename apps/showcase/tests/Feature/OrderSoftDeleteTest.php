<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Resources\OrderResource;
use App\Models\Order;
use Arqel\Table\TableQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Exercises the OrderResource trashed filter (Task 5.0 / Part B2). The
 * framework ships no soft-delete filter primitive, so OrderResource hand-rolls
 * one via SelectFilter::apply() calling withTrashed()/onlyTrashed(). These
 * assertions drive that filter through the real TableQueryBuilder pipeline and
 * fail if the filter is reverted.
 */
final class OrderSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrders(): void
    {
        Order::factory()->count(3)->create();
        Order::factory()->count(2)->create()->each->delete();
    }

    /**
     * @param  array<string, string>  $filter
     */
    private function buildCount(array $filter): int
    {
        $table = (new OrderResource)->table();
        $request = Request::create('/admin/orders', 'GET', ['filter' => $filter]);

        /** @var Builder<Order> $query */
        $query = Order::query();

        return (new TableQueryBuilder($table, $query, $request))->build()->total();
    }

    public function test_table_exposes_a_trashed_filter(): void
    {
        $filters = (new OrderResource)->table()->getFilters();

        $names = array_map(
            static fn ($filter): string => $filter->getName(),
            $filters,
        );

        $this->assertContains(
            'trashed',
            $names,
            'OrderResource table() must expose a "trashed" filter so soft-deleted orders can be surfaced.',
        );
    }

    public function test_trashed_rows_are_hidden_by_default(): void
    {
        $this->seedOrders();

        $this->assertSame(3, $this->buildCount([]));
    }

    public function test_only_trashed_filter_surfaces_soft_deleted_rows(): void
    {
        $this->seedOrders();

        $this->assertSame(2, $this->buildCount(['trashed' => 'only']));
    }

    public function test_with_trashed_filter_includes_every_row(): void
    {
        $this->seedOrders();

        $this->assertSame(5, $this->buildCount(['trashed' => 'with']));
    }
}
