# Scenario 1 — CMS Articles with versioning + restore

> **Use case:** content management system where editors publish,
> edit, and occasionally revert articles. Focus on **restore** after
> accidental edits.

## Context

The newsroom at `news.example.com` has 15 active editors. One of them,
while trying to update the slug of the article "2026 Elections —
recap", accidentally deletes the entire body and saves. Three hours
later the error is detected in production. The article needs to roll
back to its state from 3h ago **without losing later changes made to
other articles**.

This is the classic case for `arqel-dev/versioning`:

- Low-medium cardinality (~5,000 articles in total).
- Saves per article: ~20-50 over the lifecycle.
- Payload per article: 5-30 KB (markdown + metadata).
- Restore is the critical operation.
- Projected storage: ~50k versions × 15KB = ~750 MB. Tolerable.

## PHP model

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

Nothing more than `use Versionable`. The trait observes `saved` and
records the full snapshot automatically.

## Resource with History tab

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

`HistoryTab` is a future helper that renders the React component
`<VersionTimeline>` pointing to the endpoints already shipped in the package.

## Weekly prune schedule

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

For ~5,000 articles with 50 versions each, prune runs in seconds.

## React component `<VersionTimeline>`

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

## Restore with confirmation

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

## Combining with `arqel-dev/audit`

`arqel-dev/versioning` does not record _intent_ — only content. For
editorial compliance it's useful to record **who clicked Restore and
why**, separately:

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

The split is clean:

- **Versioning** answers: "what was the article X content at T?"
- **Audit** answers: "who reverted article X, when, and why?"

## Operational notes

- **Read cache**: the History tab is lazy. Don't pre-load
  `payload` — the endpoint omits it by default.
- **PII**: the `Article` model has no PII. If you add it (e.g.,
  the author's email inline), filter via accessor before save.
- **Deterministic replay**: since `restoreToVersion` creates a new
  Version, it's safe to call and undo multiple times — the history
  grows but never corrupts.
- **Backup**: the `arqel_versions` table needs to be in the backup
  plan. In a disaster, it's the only source of truth for the history.

## Real metrics (estimate)

| Metric | Value |
| --- | --- |
| Articles | 5,000 |
| Average saves per article | 30 |
| Average payload size | 12 KB |
| Versions storage | ~1.8 GB |
| Weekly prune time | < 5s |
| Restore time (single) | < 100ms |
| Coverage of "editorial ctrl+z" | 100% of edits in the last 90d |

## Related

- [README — overall comparison](./README.md)
- [E-commerce Orders — when NOT to use versioning](./ecommerce-orders.md)
- [Legal Contracts — versioning + audit combined](./legal-contracts.md)
