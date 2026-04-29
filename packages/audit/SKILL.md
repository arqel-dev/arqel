# SKILL.md — arqel/audit

> Contexto canónico para AI agents.

## Purpose

`arqel/audit` é o wrapper Arqel sobre [`spatie/laravel-activitylog`](https://spatie.be/docs/laravel-activitylog). Cobre RF-IN-04 (audit log enterprise) com convention-over-configuration: trait `LogsActivity` aplica defaults Arqel (`logOnlyDirty`, log name = `class_basename`, whitelist via `$fillable`) sem o usuário precisar copiar boilerplate em cada model.

A escolha é **integrar, não reinventar**: ActivityLog do Spatie é maduro, bem testado, com migrations + model `Activity` + relações `subject`/`causer` resolvidas. Repor isso seria desperdício.

## Status

**Entregue (AUDIT-001):**

- Esqueleto do pacote `arqel/audit` com PSR-4 `Arqel\Audit\` → `src/`, autoload-dev `Arqel\Audit\Tests\` → `tests/`
- **`Arqel\Audit\Concerns\LogsActivity`** — trait wrapper sobre `Spatie\Activitylog\Traits\LogsActivity` com defaults Arqel: `LogOptions::defaults()->logOnly($this->getAuditableAttributes())->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName(class_basename($this))`. Hook `protected function getAuditableAttributes(): array` permite subclasses customizarem o whitelist (default: `$fillable ?? ['*']`). **Decisão:** o spec original do ticket mostrava `use SpatieLogsActivity { getActivitylogOptions as _getSpatieOptions; }`, mas em `spatie/laravel-activitylog ^4.10` o método é `abstract` — o alias deixaria `_getSpatieOptions` também abstract sem implementação concreta, quebrando o instanciamento. O alias foi removido (nossa implementação concreta satisfaz o abstract direto). Apps que precisam de defaults Spatie crus podem chamar `LogOptions::defaults()` manualmente em um override
- **`Arqel\Audit\Http\Controllers\ActivityLogController`** (final) — scaffold com `index(Request)` retornando `JsonResponse` paginada (campos: `id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at`). `per_page` clampado a `1..200` (default 50). **Decisão:** scaffold devolve JSON em vez de Inertia para AUDIT-001 não fixar shape antes de o timeline UI (AUDIT-002) ditar requisitos
- **`Arqel\Audit\AuditServiceProvider`** auto-discovered via `extra.laravel.providers`. Boota via `Spatie\LaravelPackageTools\PackageServiceProvider` (`name('arqel-audit')`). **Não registra rotas** no scaffold — AUDIT-002+ adiciona quando o Inertia renderer entrar
- Testes Pest 3 + Orchestra Testbench:
  - `Feature/AuditServiceProviderTest` (2): provider boots + nome `arqel-audit`
  - `Feature/LogsActivityTraitTest` (4): create/update/delete produzem entries com `log_name = FakeAuditableModel`, properties `attributes`/`old`, `dontSubmitEmptyLogs` evita save vazio
  - `Unit/LogsActivityOptionsTest` (2): defaults da `LogOptions` (logName, logOnlyDirty, submitEmptyLogs, logAttributes)
  - `Unit/ActivityLogControllerTest` (3): `index` callable, payload paginado, clamp `per_page`
  - **Total: 11 testes** (assumindo execução pós `composer install`)
- `TestCase` registra `ActivitylogServiceProvider` + `AuditServiceProvider`, faz `loadMigrationsFrom(<vendor>/spatie/laravel-activitylog/database/migrations)` em `setUp()` (com fallback para `vendor/.../migrations`), e cria a tabela `fake_auditable_models` via `Schema::create` para alimentar os testes do trait

**Por chegar (AUDIT-002+):**

- `ActivityLogController` rendering Inertia + filtros (subject, causer, date range)
- `Activity` timeline tab embarcada em Resource detail (`showActivityLog(): bool`)
- Causer automático via Auth facade — já é default Spatie quando há `Auth::user()` no request
- Configuração para mascarar atributos sensíveis (PII, secrets) globalmente
- Retention policies / pruning command

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por defeito (exceto a trait, que é consumida por user-land Eloquent models)
- **Trait `LogsActivity` é o entry point único** — aplique no model e os defaults Arqel valem; override `getAuditableAttributes()` para refinar whitelist; override `getActivitylogOptions()` para configurações exóticas (chame `LogOptions::defaults()` se precisar do baseline do Spatie)
- **Hard dep em `spatie/laravel-activitylog ^4.10`** é design intent, não acidente: este pacote *é* o wrapper. Diferente de `arqel/tenant` (que mantém `stancl/tenancy` e `spatie/laravel-multitenancy` como `suggest`/optional), `arqel/audit` não tem razão de existir sem o ActivityLog do Spatie. A dependência também garante que migrations e bindings sejam auto-discovered no app consumidor
- `spatie/laravel-package-tools` é dep direta (não vem via `arqel/core`) porque o pacote pode ser usado standalone, sem o resto do stack Arqel

## Anti-patterns

- ❌ **Logar atributos sensíveis sem override** — `getAuditableAttributes()` defaulta a `$fillable`; se o model tem `password`, `api_token`, dados PII no `$fillable`, **override é obrigatório** para mascarar antes de cair na tabela `activity_log`
- ❌ **Logar eventos não-Eloquent via este trait** — a trait do Spatie só captura model events (created/updated/deleted/restored). Eventos de domínio (`OrderPlaced`, `UserInvited`) pertencem a um event-log separado (futuro `arqel/audit` event-log) ou ao próprio `Activity::createdEvent()` chamado manualmente
- ❌ **Usar a trait sem rodar a migration `activity_log`** — sem a tabela, qualquer save explode em runtime. A migration vem do Spatie ServiceProvider auto-discovered; em testes carregue via `loadMigrationsFrom(<vendor>/spatie/laravel-activitylog/database/migrations)`
- ❌ **Confiar no `ActivityLogController::index` como API pública estável** — é scaffold AUDIT-001. Shape vai mudar em AUDIT-002 (Inertia + filtros). Use `Spatie\Activitylog\Models\Activity::query()` direto se precisar antes de AUDIT-002 estabilizar
- ❌ **Customizar `useLogName` para algo dinâmico (ex: `auth()->id()`)** — log name é discriminator de bucket, não payload. Causer/subject já carregam quem/o-quê

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §AUDIT-001..010
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Audit
- Source: [`packages/audit/src/`](./src/)
- Tests: [`packages/audit/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [`spatie/laravel-activitylog`](https://spatie.be/docs/laravel-activitylog) (hard dep — wrapper purpose)
