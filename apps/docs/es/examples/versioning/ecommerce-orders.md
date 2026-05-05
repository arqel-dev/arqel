# Escenario 2 — E-commerce Orders: cuándo NO usar versioning

> **Caso de uso:** tienda online con millones de pedidos. Cada pedido pasa
> por estados (`pending → paid → shipped → delivered`) y rara vez es
> editado después. **Conclusión recomendada: usa `arqel-dev/audit`, NO
> versioning.**

## Contexto

El marketplace procesa 10,000 pedidos/día, ~3.6M/año. Cada `Order` tiene:

- ~30 columnas (totales, estado, direcciones normalizadas, métodos de
  pago).
- Payload JSON promedio de ~2 KB.
- Ciclo de vida: creación + 3-5 transiciones de estado + ocasionalmente una
  corrección manual del admin.

La tentación inicial es "voy a aplicar `Versionable` como hicimos en el CMS".
**Esta sección muestra por qué eso es un error serio**, y cuál es el
enfoque correcto.

## Por qué versioning es la elección equivocada aquí

### Aritmética de almacenamiento

Versioning registra un snapshot completo por save:

```
Orders per year:        3,600,000
Average saves per order:        4 (creation + 3 transitions)
Total versions/year:    14,400,000
Average payload:               2 KB
Annual storage:               ~28 GB
Accumulated storage over 5 years: ~140 GB
```

Comparado con `arqel-dev/audit` con un payload delta de ~200 bytes:

```
Events per year:        14,400,000
Average payload:               200 B
Annual storage:               ~2.7 GB
```

**Versioning cuesta ~10× más storage** y responde una pregunta que nadie
hace en este dominio ("¿cuál era el estado completo del pedido X en T?" —
casi nadie lo necesita, comparado con "¿cuándo se pagó?").

### Patrón real de query

En producción, las queries sobre los audit logs de pedidos se ven así:

- "Lista cada pedido que pasó de `paid` a `refunded` en
  noviembre" — query por `event_name` + rango de fechas.
- "¿Cuántos pedidos fueron cancelados por el cliente vs por el vendedor?" —
  query por `actor_type` + `event_name`.
- "¿Cuál es el tiempo promedio entre `paid` y `shipped`?" — dos
  queries cruzadas en el event log.

**Ninguna de estas queries necesita el snapshot completo.** Necesitan
el _evento_ — quién, cuándo, qué.

## Modelo Order — sin `Versionable`

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

Nota la **ausencia** de `use Versionable`. Este modelo no tiene el trait —
deliberadamente.

## Listener para cambios de estado (basado en audit)

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

Evento dispatched en la capa de servicio:

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

La transición misma es trivial (un `update`). El **historial vive en
el audit log**, no en el snapshot del modelo.

## Comparación directa: decisión equivocada vs correcta

| Decisión | Equivocada (versioning) | Correcta (audit) |
| --- | --- | --- |
| Storage anual | ~28 GB | ~2.7 GB |
| Tiempo promedio de save con hook | +12ms (encode payload completo) | +2ms (insert audit row) |
| Query "¿cuándo se pagó?" | Scan sobre `arqel_versions` por `versionable_id` + JSON parse | `WHERE event_name='order.paid' AND subject_id=?` (indexado) |
| Restore | No tiene sentido para pedidos | N/A |
| Compliance fiscal | OK pero sobredimensionado | OK y ligero |

## Cuándo versioning sí tendría sentido en e-commerce

No para `Order`. Pero para **`PricingRule`**, sí:

```php
final class PricingRule extends Model
{
    use Versionable;   // ← OK here

    protected $fillable = ['name', 'discount_pct', 'min_total_cents', 'active', 'priority'];
}
```

Por qué:

- **Cardinalidad baja**: 100-500 reglas en producción.
- **Saves frecuentes**: los equipos de pricing las ajustan varias veces
  por semana (~1,000 saves/mes).
- **Restore valioso**: "rollback de la regla X a la versión anterior a
  Black Friday" es una operación real.
- **Aritmética manejable**: 1,000 versiones/mes × 1 KB = 12 MB/año.
  Despreciable.

La línea que separa el _buen uso_ del _bloat_ no es el dominio (e-commerce
o no), es el **ratio entre saves y cardinalidad × utilidad de
restore**.

## Heurística rápida

> **Usa `arqel-dev/versioning` cuando el producto de `saves_per_record`
> y `total_cardinality` esté por debajo de ~1M, y cuando restore sea una
> feature explícita del producto.**

Ejemplos manejables:

- 5,000 artículos × 30 saves = 150,000 ✅
- 200 reglas de pricing × 100 saves = 20,000 ✅
- 50,000 contratos × 20 saves = 1,000,000 ⚠️ (límite — prune agresivo)
- 3.6M pedidos × 4 saves = 14.4M ❌

## ¿Y si ya apliqué `Versionable` a `Order` y está en producción?

Plan de recovery:

1. Quita `use Versionable` del modelo (los snapshots dejan de generarse).
2. Corre prune: `php artisan arqel:versions:prune --days=0` filtrado
   por `versionable_type='App\Models\Order'`.
3. Migra ETL: replicar las versiones existentes en el audit log
   (script one-shot extrayendo deltas).
4. Drop de las filas de `Order` en `arqel_versions`.
5. Validar el storage liberado.

## Relacionado

- [README — comparación general](./README.md)
- [CMS Articles — uso clásico de versioning](./cms-articles.md)
- [Legal Contracts — cuándo ambos tienen sentido](./legal-contracts.md)
