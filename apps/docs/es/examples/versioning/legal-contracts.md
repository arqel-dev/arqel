# Escenario 3 — Legal Contracts: versioning + audit combinados

> **Caso de uso:** plataforma legal-tech donde cada edición de contrato
> necesita ser preservada **inmutablemente** durante 7 años para
> compliance. Combina `arqel-dev/versioning` (snapshot) con `arqel-dev/audit`
> (contexto humano).

## Contexto

`legalflow.example.com` gestiona contratos B2B. Requisitos:

- **Inmutabilidad probatoria**: cualquier versión del contrato debe
  ser recuperable bit-a-bit, años después, en caso de disputa.
- **Cadena de aprobación trazable**: quién aprobó cada cláusula,
  IP, timestamp, razón declarada.
- **Retención legal de 7 años** (Brasil — Ley 12.682/2012 y plazos
  prescriptivos civiles).
- **GDPR / LGPD**: los contratos contienen PII (CPF, dirección, salario). El
  derecho al olvido _no se aplica_ a documentos legales activos,
  pero la aplicación debe permitir anonimización post-terminación.
- **Tamper-evidence**: hash de cada versión expuesto en la UI, idealmente
  firmado.

Aquí **versioning solo no basta** (no captura intención/aprobador),
y **audit solo no basta** (no preserva contenido bit-a-bit). Uso combinado.

## Modelo `LegalContract`

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

## Schedule de prune con retención legal

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

Importante: el prune corre **mensualmente, no semanalmente**, porque la eliminación
masiva necesita ser auditada a su vez. Si la ventana de 7 años expira,
el operador legal necesita autorizar — idealmente el job
genera un reporte antes de borrar.

## Integración con `arqel-dev/audit` para contexto humano

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

La división de responsabilidades:

| Pregunta | Quién responde |
| --- | --- |
| "¿Cuál era el texto de la cláusula 4.2 el 2026-03-15?" | `arqel-dev/versioning` |
| "¿Quién aprobó el cambio a la cláusula 4.2?" | `arqel-dev/audit` |
| "¿Desde qué IP vino la aprobación?" | `arqel-dev/audit` |
| "¿Cuál fue la razón declarada para el cambio?" | `arqel-dev/audit` |
| "¿Puedo probar que el contrato no fue alterado tras la firma?" | `arqel-dev/versioning` (hash) + `arqel-dev/audit` (evento `contract.signed`) |

## GDPR / LGPD — anonimización post-terminación

Para contratos **terminados hace más de 7 años**:

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

Puntos sutiles:

- `saveQuietly()` evita disparar el hook recursivo (que crearía
  una nueva Version de la _Version_, lo cual no tiene sentido).
- El `created_at` de la Version se preserva — el historial sigue existiendo,
  solo el contenido PII fue removido.
- Esta operación es **destructiva y auditada**: ella misma debe
  emitir un evento `compliance.anonymized` en el audit log.

## Tamper-evidence (propuesta — feature futura)

La idea es firmar cada Version con un hash determinista en save:

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

Cada Version tendría `hash` como una nueva columna, formando una
cadena estilo blockchain ligera: alterar una versión en medio de la cadena
invalida todas las posteriores. Para tamper-evidence verdadero, el
hash de la última versión del día debería publicarse en un servicio
externo de timestamp (e.g., OpenTimestamps con Bitcoin).

**Estado**: no implementado en VERS-001…007. Propuesta para
post-MVP.

## UI — mostrando la cadena probatoria

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

La timeline muestra **eventos humanos** ("Diogo aprobó — IP 200.x.x.x —
razón: 'Ajuste a la cláusula 4.2 según opinión legal'") **junto con
snapshots** (versión clickeable que abre un diff completo). Es esta fusión la que
caracteriza la aplicación legal-tech.

## Métricas reales (estimado)

| Métrica | Valor |
| --- | --- |
| Contratos activos | 50,000 |
| Versiones promedio por contrato | 8 |
| Eventos de audit por contrato | 25 |
| Storage de versiones (años 1-7) | ~25 GB |
| Storage de audit (años 1-7) | ~3 GB |
| Retención legal | 2,555 días (7 años) |
| Anonimización post-7-años | mensual, idempotente |

## Relacionado

- [README — comparación general](./README.md)
- [CMS Articles — versioning standalone](./cms-articles.md)
- [E-commerce Orders — audit standalone](./ecommerce-orders.md)
- `packages/versioning/SKILL.md`
- `PLANNING/10-fase-3-avancadas.md` § "5. Record versioning"
