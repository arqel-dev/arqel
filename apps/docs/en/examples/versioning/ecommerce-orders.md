# Scenario 2 — E-commerce Orders: when NOT to use versioning

> **Use case:** online store with millions of orders. Each order goes
> through states (`pending → paid → shipped → delivered`) and is rarely
> edited afterwards. **Recommended conclusion: use `arqel-dev/audit`, NOT
> versioning.**

## Context

The marketplace processes 10,000 orders/day, ~3.6M/year. Each `Order` has:

- ~30 columns (totals, status, normalized addresses, payment
  methods).
- Average JSON payload of ~2 KB.
- Lifecycle: creation + 3-5 status transitions + occasionally a
  manual admin correction.

The initial temptation is "I'll apply `Versionable` like we did in the CMS".
**This section shows why this is a serious mistake**, and what the
correct approach is.

## Why versioning is the wrong choice here

### Storage arithmetic

Versioning records a full snapshot per save:

```
Orders per year:        3,600,000
Average saves per order:        4 (creation + 3 transitions)
Total versions/year:    14,400,000
Average payload:               2 KB
Annual storage:               ~28 GB
Accumulated storage over 5 years: ~140 GB
```

Compared to `arqel-dev/audit` with a delta payload of ~200 bytes:

```
Events per year:        14,400,000
Average payload:               200 B
Annual storage:               ~2.7 GB
```

**Versioning costs ~10× more storage** and answers a question nobody
asks in this domain ("what was the full state of order X at T?" —
hardly anyone needs it, compared to "when was it paid?").

### Real query pattern

In production, queries on order audit logs look like:

- "List every order that moved from `paid` to `refunded` in
  November" — query by `event_name` + date range.
- "How many orders were canceled by the customer vs by the seller?" —
  query by `actor_type` + `event_name`.
- "What's the average time between `paid` and `shipped`?" — two
  cross queries on the event log.

**None of these queries needs the full snapshot.** They
need the _event_ — who, when, what.

## Order model — without `Versionable`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'status',
        'subtotal_cents',
        'shipping_cents',
        'total_cents',
        'currency',
        'paid_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'paid_at'       => 'datetime',
        'shipped_at'    => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

Note the **absence** of `use Versionable`. This model does not have the trait —
deliberately.

## Listener for state changes (audit-based)

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Arqel\Audit\Facades\Audit;

final class RecordOrderStateTransition
{
    public function handle(OrderStatusChanged $event): void
    {
        Audit::record(
            actor: $event->actor,
            event: "order.{$event->newStatus}",
            subject: $event->order,
            payload: [
                'from'              => $event->oldStatus,
                'to'                => $event->newStatus,
                'transitioned_at'   => now()->toIso8601String(),
                'reason'            => $event->reason,
            ],
        );
    }
}
```

Event dispatched in the service layer:

```php
final class TransitionOrder
{
    public function execute(Order $order, string $to, ?User $actor, ?string $reason = null): void
    {
        $from = $order->status;

        DB::transaction(function () use ($order, $to) {
            $order->update(['status' => $to]);
        });

        OrderStatusChanged::dispatch($order, $from, $to, $actor, $reason);
    }
}
```

The transition itself is trivial (an `update`). The **history lives in
the audit log**, not in the model snapshot.

## Direct comparison: wrong decision vs right decision

| Decision | Wrong (versioning) | Right (audit) |
| --- | --- | --- |
| Annual storage | ~28 GB | ~2.7 GB |
| Average save time with hook | +12ms (encode full payload) | +2ms (insert audit row) |
| "When was it paid" query | Scan over `arqel_versions` by `versionable_id` + JSON parse | `WHERE event_name='order.paid' AND subject_id=?` (indexed) |
| Restore | Doesn't make sense for orders | N/A |
| Tax compliance | OK but oversized | OK and lean |

## When versioning would make sense in e-commerce

Not for `Order`. But for **`PricingRule`**, yes:

```php
final class PricingRule extends Model
{
    use Versionable;   // ← OK here

    protected $fillable = ['name', 'discount_pct', 'min_total_cents', 'active', 'priority'];
}
```

Why:

- **Low cardinality**: 100-500 rules in production.
- **Frequent saves**: pricing teams adjust them several times per
  week (~1,000 saves/month).
- **Valuable restore**: "roll rule X back to the version before
  Black Friday" is a real operation.
- **Workable arithmetic**: 1,000 versions/month × 1 KB = 12 MB/year.
  Negligible.

The line that separates _good use_ from _bloat_ isn't the domain (e-commerce
or not), it's the **ratio between saves and cardinality × restore
utility**.

## Quick heuristic

> **Use `arqel-dev/versioning` when the product of `saves_per_record`
> and `total_cardinality` is below ~1M, and when restore is an
> explicit product feature.**

Workable examples:

- 5,000 articles × 30 saves = 150,000 ✅
- 200 pricing rules × 100 saves = 20,000 ✅
- 50,000 contracts × 20 saves = 1,000,000 ⚠️ (limit — aggressive prune)
- 3.6M orders × 4 saves = 14.4M ❌

## What if I already applied `Versionable` to `Order` and it's in production?

Recovery plan:

1. Remove `use Versionable` from the model (snapshots stop generating).
2. Run prune: `php artisan arqel:versions:prune --days=0` filtered
   by `versionable_type='App\Models\Order'`.
3. Migrate ETL: replay the existing versions into the audit log
   (one-shot script extracting deltas).
4. Drop the `Order` rows in `arqel_versions`.
5. Validate freed storage.

## Related

- [README — overall comparison](./README.md)
- [CMS Articles — classic versioning use](./cms-articles.md)
- [Legal Contracts — when both make sense](./legal-contracts.md)
