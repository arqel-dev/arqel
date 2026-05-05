# Cenário 1 — CMS Articles com versioning + restore

> **Use case:** sistema de gestão de conteúdo onde editores publicam,
> editam e ocasionalmente revertem artigos. Foco em **restore** após
> edição acidental.

## Contexto

A redação do portal `news.example.com` tem 15 editores ativos. Um
deles, ao tentar atualizar o slug do artigo "Eleições 2026 —
balanço", apaga acidentalmente o body inteiro e salva. Três horas
depois o erro é detectado em produção. O artigo precisa voltar ao
estado de 3h atrás **sem perder mudanças posteriores feitas em outros
artigos**.

Este é o caso clássico para `arqel-dev/versioning`:

- Cardinalidade baixa-média (~5.000 artigos no total).
- Saves por artigo: ~20-50 ao longo do ciclo de vida.
- Payload por artigo: 5-30 KB (markdown + metadata).
- Restore é a operação crítica.
- Storage projetado: ~50k versions × 15KB = ~750 MB. Tolerável.

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

Nada além do `use Versionable`. O trait observa `saved` e grava
snapshot completo automaticamente.

## Resource com aba History

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
                ->confirmationCopy('Você está prestes a reverter o artigo. Uma nova versão será criada (ação reversível).'),
        ];
    }
}
```

A `HistoryTab` é um helper futuro que renderiza o componente React
`<VersionTimeline>` apontando para os endpoints já entregues no pacote.

## Schedule semanal de prune

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // Mantém os últimos 90 dias OU as últimas 50 versions, o que vier primeiro.
    $schedule->command('arqel:versions:prune --days=90')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->onOneServer();
}
```

Para ~5.000 artigos com 50 versions cada, o prune roda em segundos.

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

  if (isLoading) return <div>Carregando histórico…</div>;

  return (
    <div className="space-y-3">
      {data?.versions.data.map((v) => (
        <article key={v.id} className="rounded border p-3">
          <header className="flex justify-between text-sm text-muted">
            <time dateTime={v.created_at}>
              {new Date(v.created_at).toLocaleString('pt-BR')}
            </time>
            <span>{v.user?.name ?? 'Sistema'}</span>
          </header>
          <p className="mt-2">{v.changes_summary}</p>
          {!v.is_initial && (
            <button
              type="button"
              onClick={() => setPendingRestore(v)}
              className="mt-2 text-sm text-primary"
            >
              Restaurar esta versão
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

## Restore com confirmação

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
        <DialogTitle>Restaurar versão de {new Date(version.created_at).toLocaleString('pt-BR')}?</DialogTitle>
        <DialogDescription>
          Uma nova versão será criada com este conteúdo. Você poderá desfazer
          o restore depois (ele aparecerá no histórico).
        </DialogDescription>
        <DialogFooter>
          <button onClick={onClose}>Cancelar</button>
          <button onClick={handleConfirm} className="bg-primary text-white">
            Confirmar restore
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

## Combinação com `arqel-dev/audit`

`arqel-dev/versioning` não registra _intenção_ — só conteúdo. Para
compliance editorial é útil registrar **quem clicou em Restore e
porquê**, em separado:

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

A divisão é limpa:

- **Versioning** responde: "qual era o conteúdo do artigo X em T?"
- **Audit** responde: "quem reverteu o artigo X, quando, e por quê?"

## Observações operacionais

- **Cache de leitura**: a tab History é lazy. Não pré-carregue
  `payload` — o endpoint omite por default.
- **PII**: o model `Article` não tem PII. Caso adicione (e.g.,
  e-mail do autor inline), filtre via accessor antes do save.
- **Replay determinístico**: como `restoreToVersion` cria nova
  Version, é seguro chamar e desfazer múltiplas vezes — o histórico
  cresce mas nunca corrompe.
- **Backup**: a tabela `arqel_versions` precisa estar no plano de
  backup. Em desastre, é a única fonte de verdade do histórico.

## Métricas reais (estimativa)

| Métrica | Valor |
| --- | --- |
| Artigos | 5.000 |
| Saves médios por artigo | 30 |
| Tamanho médio do payload | 12 KB |
| Storage de versions | ~1.8 GB |
| Tempo de prune semanal | < 5s |
| Tempo de restore (single) | < 100ms |
| Cobertura de "ctrl+z editorial" | 100% das edições dos últimos 90d |

## Related

- [README — comparativo geral](./README.md)
- [E-commerce Orders — quando NÃO usar versioning](./ecommerce-orders.md)
- [Legal Contracts — versioning + audit combinados](./legal-contracts.md)
