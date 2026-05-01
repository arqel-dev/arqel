# Cenário 3 — Legal Contracts: versioning + audit combinados

> **Use case:** plataforma legal-tech onde cada edição em contrato
> precisa ser preservada **imutavelmente** por 7 anos para
> compliance. Combina `arqel/versioning` (snapshot) com `arqel/audit`
> (contexto humano).

## Contexto

`legalflow.example.com` gerencia contratos B2B. Requisitos:

- **Imutabilidade probatória**: qualquer versão do contrato precisa
  ser recuperável bit-a-bit, anos depois, em caso de disputa.
- **Cadeia de aprovação rastreável**: quem aprovou cada cláusula,
  IP, timestamp, motivo declarado.
- **Retenção legal de 7 anos** (Brasil — Lei 12.682/2012 e prazos
  prescricionais civis).
- **GDPR / LGPD**: contratos contêm PII (CPF, endereço, salário). O
  direito ao esquecimento _não se aplica_ a documentos legais ativos,
  mas a aplicação precisa permitir anonimização pós-encerramento.
- **Tamper-evidence**: hash de cada versão exposto na UI, idealmente
  assinado.

Aqui **versioning sozinho não basta** (não captura intenção/aprovador),
e **audit sozinho não basta** (não preserva o conteúdo bit-a-bit). Uso
combinado.

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
    use AuditableContract;   // dispara audit events em mudanças relevantes

    protected $fillable = [
        'title',
        'parties',         // JSON — partes envolvidas (com CPF/CNPJ)
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
     * Hook usado pelo trait Versionable para sanitizar payload antes
     * de gravar snapshot. Filtra PII bruta — guardamos hash.
     *
     * Nota: este hook é proposta para futura iteração do trait
     * (atualmente o trait não chama serializing(); ver "Próximos passos").
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

## Schedule de prune com retenção legal

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // 7 anos = 2555 dias — retenção mínima exigida.
    // Não usamos --keep, pois precisamos manter TODAS versions
    // dentro da janela legal, independente da contagem.
    $schedule->command('arqel:versions:prune --days=2555')
        ->monthly()
        ->onOneServer();
}
```

Importante: o prune corre **mensalmente, não semanalmente**, porque
remoção em massa precisa ser auditada por sua vez. Se a janela de 7
anos vence, o operador legal precisa autorizar — idealmente o job
gera um relatório antes de apagar.

## Integração `arqel/audit` para contexto humano

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

A divisão de responsabilidades fica:

| Pergunta | Quem responde |
| --- | --- |
| "Qual era o texto da cláusula 4.2 em 2026-03-15?" | `arqel/versioning` |
| "Quem aprovou a alteração da cláusula 4.2?" | `arqel/audit` |
| "De que IP veio a aprovação?" | `arqel/audit` |
| "Qual o motivo declarado da alteração?" | `arqel/audit` |
| "Posso provar que o contrato não foi alterado depois de assinado?" | `arqel/versioning` (hash) + `arqel/audit` (evento `contract.signed`) |

## GDPR / LGPD — anonimização pós-encerramento

Para contratos **terminados há mais de 7 anos**:

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
            // Anonimiza payload das versions preservando estrutura.
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
                        $payload['body_markdown'] = '[ANONIMIZADO]';
                    }
                    // payload é JSON; gravação direta preservando created_at.
                    $version->forceFill(['payload' => $payload])->saveQuietly();
                });
        }

        return self::SUCCESS;
    }
}
```

Pontos sutis:

- `saveQuietly()` evita disparar hook recursivo (que criaria nova
  Version do _Version_, o que não faz sentido).
- `created_at` da Version é preservado — o histórico continua existindo,
  só o conteúdo PII foi removido.
- Esta operação é **destrutiva e auditada**: ela própria deve gerar
  evento `compliance.anonymized` no audit log.

## Tamper-evidence (proposta — feature future)

A ideia é assinar cada Version com hash determinístico ao gravar:

```php
// pseudo-code para iteração futura do trait

protected function generateVersionHash(array $payload): string
{
    return hash('sha256', json_encode([
        'payload'    => $payload,
        'created_at' => now()->toIso8601String(),
        'previous'   => $this->currentVersion()?->hash,
    ], JSON_THROW_ON_ERROR));
}
```

Cada Version teria `hash` como coluna nova, formando uma cadeia
estilo blockchain leve: alterar uma version no meio da cadeia
invalida todas as posteriores. Para tamper-evidence verdadeira, o
hash da última version do dia deve ser publicado num timestamp
service externo (e.g., OpenTimestamps com Bitcoin).

**Status**: não implementado em VERS-001…007. Proposta para
post-MVP.

## UI — exibindo a cadeia probatória

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

  // Merge cronológico de versions (snapshot) + events (contexto).
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

A timeline mostra **eventos humanos** ("Diogo aprovou — IP 200.x.x.x —
motivo: 'Ajuste de cláusula 4.2 conforme parecer jurídico'") **junto com
snapshots** (versão clicável que abre diff completo). É essa fusão que
caracteriza a aplicação legal-tech.

## Métricas reais (estimativa)

| Métrica | Valor |
| --- | --- |
| Contratos ativos | 50.000 |
| Versions médias por contrato | 8 |
| Eventos audit por contrato | 25 |
| Storage versions (anos 1-7) | ~25 GB |
| Storage audit (anos 1-7) | ~3 GB |
| Retenção legal | 2.555 dias (7 anos) |
| Anonimização pós-7-anos | mensal, idempotente |

## Related

- [README — comparativo geral](./README.md)
- [CMS Articles — versioning standalone](./cms-articles.md)
- [E-commerce Orders — audit standalone](./ecommerce-orders.md)
- `packages/versioning/SKILL.md`
- `PLANNING/10-fase-3-avancadas.md` § "5. Record versioning"
