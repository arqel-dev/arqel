# Cenário 2 — E-commerce Orders: quando NÃO usar versioning

> **Use case:** loja online com milhões de pedidos. Cada pedido passa
> por estados (`pending → paid → shipped → delivered`) e é raramente
> editado depois. **Conclusão recomendada: usar `arqel-dev/audit`, NÃO
> versioning.**

## Contexto

Marketplace processa 10.000 pedidos/dia, ~3.6M/ano. Cada `Order` tem:

- ~30 colunas (totals, status, addresses normalizados, métodos de
  pagamento).
- Payload JSON médio de ~2 KB.
- Ciclo de vida: criação + 3-5 transições de status + raramente uma
  correção manual de admin.

A tentação inicial é "vou aplicar `Versionable` igual fizemos no CMS".
**Esta seção mostra por que isso é um erro grave**, e qual a abordagem
correta.

## Por que versioning é a escolha errada aqui

### Aritmética do storage

Versioning grava snapshot completo a cada save:

```
Pedidos por ano:        3.600.000
Saves médios por pedido:        4 (criação + 3 transições)
Total de versions/ano:  14.400.000
Payload médio:                2 KB
Storage anual:                ~28 GB
Storage acumulado em 5 anos: ~140 GB
```

Comparado a `arqel-dev/audit` com payload delta de ~200 bytes:

```
Eventos por ano:        14.400.000
Payload médio:               200 B
Storage anual:               ~2.7 GB
```

**Versioning custa ~10× mais storage** e responde a uma pergunta que
ninguém faz nesse domínio ("qual era o estado completo do pedido X em
T?" — quase nunca alguém precisa, comparado a "quando foi pago?").

### Padrão de query real

Em produção, queries em audit log de pedidos ficam tipo:

- "Liste todos os pedidos que mudaram de `paid` para `refunded` em
  novembro" — query por `event_name` + range de data.
- "Quantos pedidos foram cancelados pelo customer vs pelo seller?" —
  query por `actor_type` + `event_name`.
- "Qual o tempo médio entre `paid` e `shipped`?" — duas queries
  cruzadas no event log.

**Nenhuma dessas queries precisa do snapshot completo.** Elas
precisam do _evento_ — quem, quando, o quê.

## Modelo Order — sem `Versionable`

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

Note a **ausência** de `use Versionable`. Esse model não tem o trait —
deliberadamente.

## Listener para state changes (audit-based)

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

Disparo do evento na camada de service:

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

A transição em si é trivial (um `update`). O **histórico vive no
audit log**, não no snapshot do model.

## Comparação direta: decisão errada vs correta

| Decisão | Errada (versioning) | Correta (audit) |
| --- | --- | --- |
| Storage anual | ~28 GB | ~2.7 GB |
| Tempo médio do save com hook | +12ms (encode payload completo) | +2ms (insert audit row) |
| Query "quando foi pago" | Scan em `arqel_versions` por `versionable_id` + parse de JSON | `WHERE event_name='order.paid' AND subject_id=?` (indexado) |
| Restore | Não faz sentido para pedidos | N/A |
| Compliance fiscal | OK mas oversized | OK e enxuto |

## Quando versioning faria sentido em e-commerce

Não para `Order`. Mas para **`PricingRule`** sim:

```php
final class PricingRule extends Model
{
    use Versionable;   // ← OK aqui

    protected $fillable = ['name', 'discount_pct', 'min_total_cents', 'active', 'priority'];
}
```

Por quê:

- **Cardinalidade baixa**: 100-500 rules em produção.
- **Saves frequentes**: equipes de pricing ajustam várias vezes por
  semana (~1.000 saves/mês).
- **Restore valioso**: "voltar a regra X para a versão antes do
  Black Friday" é uma operação real.
- **Aritmética cabível**: 1.000 versions/mês × 1 KB = 12 MB/ano.
  Pinga.

A linha que separa _bom uso_ de _bloat_ não é o domínio (e-commerce
ou não), é a **razão entre saves e cardinalidade × utilidade do
restore**.

## Heurística rápida

> **Use `arqel-dev/versioning` quando o produto entre `saves_por_record`
> e `cardinalidade_total` está abaixo de ~1M, e quando restore é uma
> feature explícita do produto.**

Exemplos cabíveis:

- 5.000 articles × 30 saves = 150.000 ✅
- 200 pricing rules × 100 saves = 20.000 ✅
- 50.000 contracts × 20 saves = 1.000.000 ⚠️ (limite — prune agressivo)
- 3.6M orders × 4 saves = 14.4M ❌

## E se eu já apliquei `Versionable` em `Order` e está em produção?

Plano de recuperação:

1. Remover o `use Versionable` do model (snapshots param de gerar).
2. Rodar prune: `php artisan arqel:versions:prune --days=0` filtrado
   por `versionable_type='App\Models\Order'`.
3. Migrar ETL: replay das versions existentes para o audit log
   (script one-shot extraindo deltas).
4. Drop dos rows de `Order` em `arqel_versions`.
5. Validar storage liberado.

## Related

- [README — comparativo geral](./README.md)
- [CMS Articles — uso clássico de versioning](./cms-articles.md)
- [Legal Contracts — quando ambos fazem sentido](./legal-contracts.md)
