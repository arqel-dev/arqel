# Escenario 1 — CMS Articles con versioning + restore

> **Caso de uso:** sistema de gestión de contenido donde los editores publican,
> editan y ocasionalmente revierten artículos. Foco en **restore** tras
> ediciones accidentales.

## Contexto

La redacción de `news.example.com` tiene 15 editores activos. Uno de ellos,
mientras intenta actualizar el slug del artículo "Elecciones 2026 —
recap", borra accidentalmente todo el body y guarda. Tres horas
después se detecta el error en producción. El artículo necesita hacer rollback
a su estado de hace 3h **sin perder los cambios posteriores hechos a
otros artículos**.

Este es el caso clásico para `arqel-dev/versioning`:

- Cardinalidad baja-media (~5,000 artículos en total).
- Saves por artículo: ~20-50 a lo largo del ciclo de vida.
- Payload por artículo: 5-30 KB (markdown + metadata).
- Restore es la operación crítica.
- Storage proyectado: ~50k versiones × 15KB = ~750 MB. Tolerable.

## Modelo PHP

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Arqel\Versioning\Concerns\Versionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Article extends Model
{
    use Versionable;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'excerpt',
        'status',
        'published_at',
        'author_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
```

Nada más que `use Versionable`. El trait observa `saved` y
registra el snapshot completo automáticamente.

## Resource con tab History

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Article;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\Tabs\HistoryTab;

final class ArticleResource extends Resource
{
    public static string $model = Article::class;

    public static function tabs(): array
    {
        return [
            HistoryTab::make()
                ->endpoint('arqel.versioning.history')
                ->restoreEndpoint('arqel.versioning.restore')
                ->confirmationCopy('You are about to revert the article. A new version will be created (reversible action).'),
        ];
    }
}
```

`HistoryTab` es un helper futuro que renderiza el componente React
`<VersionTimeline>` apuntando a los endpoints ya incluidos en el paquete.

## Schedule semanal de prune

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Keep the last 90 days OR the last 50 versions, whichever comes first.
    $schedule->command('arqel:versions:prune --days=90')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->onOneServer();
}
```

Para ~5,000 artículos con 50 versiones cada uno, el prune corre en segundos.

## Componente React `<VersionTimeline>`

```tsx
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useArqelEndpoint } from '@arqel-dev/core/hooks';

type VersionItem = {
  id: number;
  created_at: string;
  changes_summary: string;
  changes: Record<string, [unknown, unknown]>;
  user: { id: number; name: string } | null;
  is_initial: boolean;
};

export function VersionTimeline({
  resource,
  recordId,
}: {
  resource: string;
  recordId: number;
}) {
  const { data, isLoading } = useArqelEndpoint<{
    versions: { data: VersionItem[]; total: number };
    meta: { keep_versions: number };
  }>('arqel.versioning.history', { resource, id: recordId });

  const [pendingRestore, setPendingRestore] = useState<VersionItem | null>(null);

  if (isLoading) return <div>Loading history…</div>;

  return (
    <div className="space-y-3">
      {data?.versions.data.map((v) => (
        <article key={v.id} className="rounded border p-3">
          <header className="flex justify-between text-sm text-muted">
            <time dateTime={v.created_at}>
              {new Date(v.created_at).toLocaleString('en-US')}
            </time>
            <span>{v.user?.name ?? 'System'}</span>
          </header>
          <p className="mt-2">{v.changes_summary}</p>
          {!v.is_initial && (
            <button
              type="button"
              onClick={() => setPendingRestore(v)}
              className="mt-2 text-sm text-primary"
            >
              Restore this version
            </button>
          )}
        </article>
      ))}

      {pendingRestore && (
        <RestoreConfirmDialog
          version={pendingRestore}
          resource={resource}
          recordId={recordId}
          onClose={() => setPendingRestore(null)}
        />
      )}
    </div>
  );
}
```

## Restore con confirmación

```tsx
function RestoreConfirmDialog({
  version,
  resource,
  recordId,
  onClose,
}: {
  version: VersionItem;
  resource: string;
  recordId: number;
  onClose: () => void;
}) {
  const handleConfirm = () => {
    router.post(
      route('arqel.versioning.restore', {
        resource,
        id: recordId,
        versionId: version.id,
      }),
      {},
      {
        onSuccess: () => {
          onClose();
          router.reload({ only: ['record', 'versions'] });
        },
      },
    );
  };

  return (
    <Dialog open onOpenChange={onClose}>
      <DialogContent>
        <DialogTitle>Restore version from {new Date(version.created_at).toLocaleString('en-US')}?</DialogTitle>
        <DialogDescription>
          A new version will be created with this content. You will be able to undo
          the restore later (it will appear in the history).
        </DialogDescription>
        <DialogFooter>
          <button onClick={onClose}>Cancel</button>
          <button onClick={handleConfirm} className="bg-primary text-white">
            Confirm restore
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

## Combinando con `arqel-dev/audit`

`arqel-dev/versioning` no registra _intención_ — solo contenido. Para
compliance editorial es útil registrar **quién hizo clic en Restore y
por qué**, separadamente:

```php
// app/Http/Listeners/RecordRestoreEvent.php

final class RecordRestoreEvent
{
    public function handle(RestorePerformed $event): void
    {
        AuditEvent::record(
            actor: $event->user,
            event: 'article.restored',
            subject: $event->record,
            payload: [
                'restored_to_version_id' => $event->targetVersionId,
                'new_version_id'         => $event->newVersionId,
                'reason'                 => request('reason'),
                'ip'                     => request()->ip(),
            ],
        );
    }
}
```

La división es limpia:

- **Versioning** responde: "¿cuál era el contenido del artículo X en T?"
- **Audit** responde: "¿quién revirtió el artículo X, cuándo y por qué?"

## Notas operacionales

- **Read cache**: el tab History es lazy. No pre-cargues
  `payload` — el endpoint lo omite por defecto.
- **PII**: el modelo `Article` no tiene PII. Si la añades (e.g.,
  el email del autor inline), filtra vía accessor antes de save.
- **Replay determinista**: como `restoreToVersion` crea una nueva
  Version, es seguro llamarlo y deshacer múltiples veces — el historial
  crece pero nunca se corrompe.
- **Backup**: la tabla `arqel_versions` necesita estar en el plan
  de backup. En un desastre, es la única fuente de verdad para el historial.

## Métricas reales (estimado)

| Métrica | Valor |
| --- | --- |
| Artículos | 5,000 |
| Saves promedio por artículo | 30 |
| Tamaño promedio de payload | 12 KB |
| Storage de versiones | ~1.8 GB |
| Tiempo de prune semanal | < 5s |
| Tiempo de restore (único) | < 100ms |
| Cobertura de "ctrl+z editorial" | 100% de las ediciones en los últimos 90d |

## Relacionado

- [README — comparación general](./README.md)
- [E-commerce Orders — cuándo NO usar versioning](./ecommerce-orders.md)
- [Legal Contracts — versioning + audit combinados](./legal-contracts.md)
