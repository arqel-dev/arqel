# Scenario 3 — Legal Contracts: versioning + audit combined

> **Use case:** legal-tech platform where every contract edit
> needs to be preserved **immutably** for 7 years for
> compliance. Combines `arqel-dev/versioning` (snapshot) with `arqel-dev/audit`
> (human context).

## Context

`legalflow.example.com` manages B2B contracts. Requirements:

- **Evidentiary immutability**: any version of the contract must
  be recoverable bit-by-bit, years later, in case of dispute.
- **Traceable approval chain**: who approved each clause,
  IP, timestamp, declared reason.
- **7-year legal retention** (Brazil — Law 12,682/2012 and civil
  prescriptive periods).
- **GDPR / LGPD**: contracts contain PII (CPF, address, salary). The
  right to be forgotten _does not apply_ to active legal documents,
  but the application must allow post-termination anonymization.
- **Tamper-evidence**: hash of each version exposed in the UI, ideally
  signed.

Here **versioning alone is not enough** (does not capture intent/approver),
and **audit alone is not enough** (does not preserve content bit-by-bit). Combined use.

## `LegalContract` model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AuditableContract;
use Arqel\Versioning\Concerns\Versionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LegalContract extends Model
{
    use Versionable;
    use AuditableContract;   // dispatches audit events on relevant changes

    protected $fillable = [
        'title',
        'parties',         // JSON — involved parties (with CPF/CNPJ)
        'body_markdown',
        'effective_date',
        'expires_at',
        'status',          // draft | under_review | signed | terminated
        'compliance_tag',  // e.g., 'NDA', 'SLA', 'EMPLOYMENT'
    ];

    protected $casts = [
        'parties'        => 'array',
        'effective_date' => 'date',
        'expires_at'     => 'date',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hook used by the Versionable trait to sanitize the payload before
     * recording the snapshot. Filters raw PII — we keep a hash.
     *
     * Note: this hook is a proposal for a future iteration of the trait
     * (currently the trait does not call serializing(); see "Next steps").
     */
    public function serializingForVersion(array $attributes): array
    {
        if (isset($attributes['parties']) && is_array($attributes['parties'])) {
            $attributes['parties'] = array_map(
                static fn (array $party) => [
                    ...$party,
                    'cpf'  => isset($party['cpf'])  ? hash('sha256', $party['cpf'])  : null,
                    'cnpj' => isset($party['cnpj']) ? hash('sha256', $party['cnpj']) : null,
                ],
                $attributes['parties'],
            );
        }

        return $attributes;
    }
}
```

## Prune schedule with legal retention

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // 7 years = 2555 days — required minimum retention.
    // We don't use --keep, since we need to keep ALL versions
    // within the legal window, regardless of count.
    $schedule->command('arqel:versions:prune --days=2555')
        ->monthly()
        ->onOneServer();
}
```

Important: prune runs **monthly, not weekly**, because mass
removal needs to be audited in turn. If the 7-year window expires,
the legal operator needs to authorize — ideally the job
generates a report before deleting.

## `arqel-dev/audit` integration for human context

```php
<?php

declare(strict_types=1);

namespace App\Concerns;

use Arqel\Audit\Facades\Audit;
use Illuminate\Support\Facades\Auth;

trait AuditableContract
{
    public static function bootAuditableContract(): void
    {
        static::saved(function (self $contract): void {
            $isCreate = $contract->wasRecentlyCreated;

            Audit::record(
                actor: Auth::user(),
                event: $isCreate ? 'contract.drafted' : 'contract.amended',
                subject: $contract,
                payload: [
                    'compliance_tag' => $contract->compliance_tag,
                    'status'         => $contract->status,
                    'changes'        => array_keys($contract->getChanges()),
                    'ip'             => request()?->ip(),
                    'user_agent'     => request()?->userAgent(),
                    'reason'         => request()?->input('amendment_reason'),
                ],
            );
        });

        static::deleted(function (self $contract): void {
            Audit::record(
                actor: Auth::user(),
                event: 'contract.archived',
                subject: $contract,
                payload: ['archived_at' => now()->toIso8601String()],
            );
        });
    }
}
```

The split of responsibilities:

| Question | Who answers |
| --- | --- |
| "What was the text of clause 4.2 on 2026-03-15?" | `arqel-dev/versioning` |
| "Who approved the change to clause 4.2?" | `arqel-dev/audit` |
| "From which IP did the approval come?" | `arqel-dev/audit` |
| "What was the declared reason for the change?" | `arqel-dev/audit` |
| "Can I prove the contract has not been altered after signing?" | `arqel-dev/versioning` (hash) + `arqel-dev/audit` (`contract.signed` event) |

## GDPR / LGPD — post-termination anonymization

For contracts **terminated more than 7 years ago**:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LegalContract;
use Arqel\Versioning\Models\Version;
use Illuminate\Console\Command;

final class AnonymizeExpiredContractsCommand extends Command
{
    protected $signature = 'contracts:anonymize-expired';

    public function handle(): int
    {
        $cutoff = now()->subYears(7);

        $contracts = LegalContract::query()
            ->where('status', 'terminated')
            ->where('expires_at', '<', $cutoff)
            ->get();

        foreach ($contracts as $contract) {
            // Anonymize the version payloads while preserving structure.
            Version::query()
                ->where('versionable_type', LegalContract::class)
                ->where('versionable_id', $contract->id)
                ->each(function (Version $version): void {
                    $payload = $version->payload;
                    if (isset($payload['parties'])) {
                        $payload['parties'] = array_map(
                            static fn () => ['anonymized' => true],
                            $payload['parties'],
                        );
                    }
                    if (isset($payload['body_markdown'])) {
                        $payload['body_markdown'] = '[ANONYMIZED]';
                    }
                    // payload is JSON; direct write preserving created_at.
                    $version->forceFill(['payload' => $payload])->saveQuietly();
                });
        }

        return self::SUCCESS;
    }
}
```

Subtle points:

- `saveQuietly()` avoids triggering the recursive hook (which would
  create a new Version of the _Version_, which makes no sense).
- The Version's `created_at` is preserved — the history continues to exist,
  only the PII content was removed.
- This operation is **destructive and audited**: it must itself
  emit a `compliance.anonymized` event in the audit log.

## Tamper-evidence (proposal — future feature)

The idea is to sign each Version with a deterministic hash on save:

```php
// pseudo-code for a future iteration of the trait

protected function generateVersionHash(array $payload): string
{
    return hash('sha256', json_encode([
        'payload'    => $payload,
        'created_at' => now()->toIso8601String(),
        'previous'   => $this->currentVersion()?->hash,
    ], JSON_THROW_ON_ERROR));
}
```

Each Version would have `hash` as a new column, forming a
lightweight blockchain-style chain: altering a version in the middle of the chain
invalidates all subsequent ones. For true tamper-evidence, the
hash of the day's last version should be published to an external
timestamp service (e.g., OpenTimestamps with Bitcoin).

**Status**: not implemented in VERS-001…007. Proposal for
post-MVP.

## UI — displaying the evidentiary chain

```tsx
function ContractAuditTrail({ contractId }: { contractId: number }) {
  const { data: versions } = useArqelEndpoint('arqel.versioning.history', {
    resource: 'legal-contracts',
    id: contractId,
  });
  const { data: events } = useArqelEndpoint('arqel.audit.events', {
    subject_type: 'App\\Models\\LegalContract',
    subject_id: contractId,
  });

  // Chronological merge of versions (snapshot) + events (context).
  const merged = mergeByTimestamp(versions, events);

  return (
    <ol className="space-y-4">
      {merged.map((entry) =>
        entry.kind === 'version' ? (
          <VersionCard key={`v-${entry.id}`} version={entry} />
        ) : (
          <AuditEventCard key={`a-${entry.id}`} event={entry} />
        ),
      )}
    </ol>
  );
}
```

The timeline shows **human events** ("Diogo approved — IP 200.x.x.x —
reason: 'Adjustment to clause 4.2 per legal opinion'") **alongside
snapshots** (clickable version that opens a full diff). It's this fusion that
characterizes the legal-tech application.

## Real metrics (estimate)

| Metric | Value |
| --- | --- |
| Active contracts | 50,000 |
| Average versions per contract | 8 |
| Audit events per contract | 25 |
| Versions storage (years 1-7) | ~25 GB |
| Audit storage (years 1-7) | ~3 GB |
| Legal retention | 2,555 days (7 years) |
| Post-7-year anonymization | monthly, idempotent |

## Related

- [README — overall comparison](./README.md)
- [CMS Articles — versioning standalone](./cms-articles.md)
- [E-commerce Orders — audit standalone](./ecommerce-orders.md)
- `packages/versioning/SKILL.md`
- `PLANNING/10-fase-3-avancadas.md` § "5. Record versioning"
