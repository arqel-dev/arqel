# Export Download Ownership — Design (#381)

**Data:** 2026-07-17
**Tipo:** fix (segurança — IDOR) • **Pacote:** `arqel/export` (+ wiring em `arqel/core`)
**Issue:** #381 — `ExportDownloadController` lacks ownership check.

## Contexto & bug

`ExportDownloadController::download($exportId)` serve o arquivo `export-<uuid>.<ext>` por
UUID (glob no disco), com a rota `['web','auth']`-gated. Mas **não há check de ownership**:
qualquer usuário autenticado que conheça (ou adivinhe) o UUID baixa qualquer export de
qualquer usuário — **IDOR entre usuários autenticados**. O docblock admite isso e delega ao
app consumidor ("MUST wrap with `can:download-exports`"), mas o pacote não dá as ferramentas
(sem Export model, sem ownership column).

O UUID é não-enumerável (`Str::uuid()`), o que mitiga parcialmente — mas não é ownership.

## Decisões (do brainstorming)

1. **Persistência:** um `Export` Eloquent model + tabela `arqel_exports` grava a associação
   export→dono. Escolhido sobre signed-URL (permite expiry + listagem; sem URL gigante).
2. **Captura do dono:** `owner_user_id = (string) auth()->id()` na criação (no `bulkAction`,
   onde há request). `auth()->id()` retorna `int|string|null` — **não acopla à User class**.
3. **Fail-closed:** export sem `Export` record, sem dono (`owner_user_id === null`: legacy/CLI),
   ou dono ≠ autenticado → **404** (não 403 — não vaza existência). Exports pré-migração
   (arquivos órfãos no disco) deixam de ser baixáveis: aceitável (segurança > acesso legado).

## Arquitetura

| Artefato | Papel |
|---|---|
| `packages/export/database/migrations/..._create_exports_table.php` | tabela `arqel_exports` |
| `packages/export/src/Models/Export.php` (novo, `final`) | model uuid-PK, sem FK ao User |
| `ExportServiceProvider::configurePackage` | `->hasMigration('create_arqel_exports_table')` (padrão Spatie, como marketplace) |
| `ExportAction::execute()` | cria o `Export` row (dono via `auth()->id()`) antes de gerar o arquivo; usa `$export->id` como o uuid do arquivo |
| `ExportDownloadController::download` | resolve via `Export::find`, aplica o ownership check fail-closed |

## Migration

`arqel_exports` (prefixo coerente com `arqel_marketplace_*`/`arqel_notifications`):

```php
Schema::create('arqel_exports', function (Blueprint $table): void {
    $table->uuid('id')->primary();                         // = export-<uuid> no disco
    $table->string('owner_user_id')->nullable()->index();  // int OU uuid → string cobre ambos
    $table->string('format', 16);
    $table->string('path');
    $table->timestamp('expires_at')->nullable()->index();  // coluna já existe; expiry-cleanup é follow-up
    $table->timestamps();
});
```

Registro: `->hasMigration('create_arqel_exports_table')` no `configurePackage` (o
`ExportServiceProvider` já estende `Spatie\LaravelPackageTools\PackageServiceProvider`).
O Spatie tools gerencia o load — **não** usar `loadMigrationsFrom` manual (evita o
double-load gotcha).

## Export model

`packages/export/src/Models/Export.php`:

```php
final class Export extends Model
{
    protected $table = 'arqel_exports';
    public $incrementing = false;      // uuid PK
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];
    // @internal (ADR-019).
}
```

Sem `belongsTo(User)` — `owner_user_id` é só um valor comparado a `auth()->id()`, mantendo o
pacote desacoplado da User class do app.

## Wiring da criação (grava o dono)

`ExportAction::execute()` — onde hoje gera `export-<Str::uuid()>.csv`, cria o `Export` primeiro:

```php
$export = Export::create([
    'id' => (string) Str::uuid(),
    'owner_user_id' => auth()->id() !== null ? (string) auth()->id() : null,
    'format' => $this->format->value,
    'path' => $destination,          // set após resolver o path
    'expires_at' => null,
]);
$filename = 'export-'.$export->id.'.'.$this->format->extension();
```

- `auth()->id()` = Laravel facade (`int|string|null`), sem importar a User class. `null` no
  CLI → owner null → fail-closed no download.
- **Async (`ProcessExportJob`):** CONFIRMADO — o `ProcessExportJob` não é dispatchado por
  ninguém no fluxo atual (a heurística de queue é deferida per o `ExportAction` docblock). O
  `execute()` é 100% sync in-process. Então **o wiring do `Export` record é só num lugar
  (`ExportAction::execute()`)** — o job não precisa ser tocado neste fix. (Se/quando o async
  for wired, o `Export` record já deve existir antes do dispatch, mas isso é escopo futuro.)
- Se a criação do `Export` falhar (DB down), o export falha com flash de erro (melhor que um
  arquivo órfão não-baixável) — coerente com o try/catch existente de `execute()`.

`ResourceController::resolveDownloadUrl` — inalterado: já deriva o uuid do filename
`export-<id>` e monta a rota `arqel.export.download`.

## ExportDownloadController — ownership check

```php
public function download(string $exportId, Request $request): BinaryFileResponse
{
    if (preg_match(self::UUID_PATTERN, $exportId) !== 1) {
        abort(400, $this->message('arqel::messages.export.invalid_id', 'Invalid export id.'));
    }

    /** @var Export|null $export */
    $export = Export::find($exportId);
    if ($export === null) {
        abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
    }

    $userId = $request->user()?->getAuthIdentifier();
    if ($export->owner_user_id === null
        || $userId === null
        || (string) $export->owner_user_id !== (string) $userId) {
        abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
    }

    // (a lógica de glob/404-ambíguo/serve-file existente permanece)
}
```

**Segurança:**
- **404, não 403**, em toda falha de ownership — não vaza a existência de exports de outros
  (anti-enumeração via status).
- **Fail-closed** em 3 condições: record ausente, dono null (legacy/CLI), dono ≠ autenticado.
- **Comparação `(string)`** dos dois lados — robusto a int-vs-uuid PK.
- **`getAuthIdentifier()`** — não acopla à User class (mesma técnica do `PublisherPayoutsController`).
- **Docblock reescrito:** remove o "SECURITY NOTE: does NOT enforce ownership… consumer's
  responsibility" — o pacote agora **enforça** ownership.

## Testes (Pest — rodável localmente)

**`ExportOwnershipTest` (Feature — a prova do fix):**
- user A baixa o próprio export → 200 + CSV.
- **user B tenta baixar o export de A (com o uuid) → 404** (o IDOR fechado; falha na baseline).
- export `owner_user_id = null` (legacy/CLI) → 404 p/ qualquer autenticado (fail-closed).
- não-autenticado → 401/redirect (middleware `auth`, existente).
- uuid inexistente → 404.
- assere **404 não 403** (anti-enumeração).
- int-vs-string: user PK int `42` + `owner_user_id "42"` → match; `42` vs `"7"` → 404.

**`ExportCreatesOwnedRecordTest` (Feature):**
- bulk export como user `42` → `Export` row com `owner_user_id "42"`, `id` = uuid do arquivo,
  `format`/`path` corretos; `download_url` aponta a `export-<Export.id>/download`.

**`ExportModelTest` (Unit):** uuid PK (`incrementing=false`, `keyType=string`), cast `expires_at`.

**Migration test (Testbench):** `arqel_exports` cria com as colunas certas.

**Regressão:** `BulkExportRoundTripTest` etc. continuam passando (o `download_url` flow não
muda, só ganha o `Export` record); ajustar os que assumem "sem tabela" para criar o record.

Cobertura-alvo: export core ≥90%; `ExportOwnershipTest` cobre 100% do novo caminho de segurança.

## Fora de escopo (follow-up)

- **Expiry cleanup** (job que apaga exports `expires_at` passados + arquivos) — a coluna existe;
  o cron/limpeza é separado.
- **Listagem de exports do usuário** (UI "meus exports") — o model habilita, mas é feature nova.
- **Signed URLs** — a tabela + auth já cobrem o IDOR; assinatura é defesa-em-profundidade opcional.
