# SKILL.md — arqel/workflow

> Contexto canônico para AI agents trabalhando no pacote `arqel/workflow`.

## Purpose

`arqel/workflow` entrega o sistema de **state machines** do Arqel: define os estados possíveis de um model Eloquent, suas metadatas de UI (label/color/icon) e a lista de transições válidas entre eles. Cobre RF-IN-06.

A escolha é **integrar quando útil, sem amarrar**: a integração canônica é com [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) (state classes + transition classes), mas o pacote é totalmente *duck-typed* — funciona também com PHP enums, slugs string ou tokens custom. `spatie/laravel-model-states` fica em `suggest:` no composer.json, nunca em `require`.

## Status

**Entregue (WF-001):**

- Esqueleto do pacote `arqel/workflow` com PSR-4 `Arqel\Workflow\` → `src/`, autoload-dev `Arqel\Workflow\Tests\` → `tests/`.
- Path repository para `arqel/core` em `composer.json`; root `composer.json` adicionou `arqel/workflow: "@dev"` em `require-dev` (alfabético).
- **`Arqel\Workflow\WorkflowServiceProvider`** auto-discovered via `extra.laravel.providers`. Boota via `Spatie\LaravelPackageTools\PackageServiceProvider` (`name('arqel-workflow')`, `hasConfigFile('arqel-workflow')`).
- **`config/arqel-workflow.php`** publicável com chaves `default_color` (`'secondary'`) e `default_icon` (`'circle'`) — fallbacks que o futuro visualizer React (WF-005) consome quando uma state declara metadata parcial.

**Entregue (WF-002):**

- **`Arqel\Workflow\WorkflowDefinition`** (final) — fluent builder:
  - `make(string $field): self` — factory; lança `InvalidArgumentException` se vazio.
  - `states(array): self` — recebe `array<string, array{label?, color?, icon?}>`. As chaves são tipicamente `class-string<State>` do spatie, mas qualquer string não-vazia funciona (PHP enum value, slug). Labels ausentes são derivadas via PascalCase → "Pending Payment". `color`/`icon` ausentes caem em `'secondary'`/`'circle'`.
  - `transitions(array $classes): self` — recebe `list<class-string>`.
  - `getField(): string`, `getStates(): array`, `getTransitions(): array`, `getStateMetadata(string): ?array`.
  - `toArray(): array` — payload `{field, states, transitions}` para serialização Inertia/React.
- **`Arqel\Workflow\Concerns\HasWorkflow`** — trait consumido por user-land Eloquent models:
  - método abstrato esperado: `arqelWorkflow(): WorkflowDefinition` (declarado via `@method` no doc-block + chamado dinamicamente).
  - `getCurrentStateMetadata(): ?array` — lê `$this->{$definition->getField()}`, resolve a chave (FQCN para objetos, `value` para `BackedEnum`, string própria para strings) e devolve `getStateMetadata(...)`. Retorna `null` quando o campo está vazio ou a state não está registrada.
  - `getAvailableTransitions(): list<class-string>` — itera `$definition->getTransitions()` e inclui cada transição que: (a) não tem `from()` estático (sempre disponível), ou (b) tem `from(): array` cujo retorno contém o key da state atual. Resolução de classes ausentes (`class_exists` falha) → exclui silenciosamente.
- **Fixtures de teste** (`tests/Fixtures/`): `PendingState`/`PaidState`/`ShippedState`/`CancelledState` (stubs neutros, NÃO estendem nenhuma classe spatie) + `PendingToPaid`/`PaidToShipped`/`AnyToCancelled` (transições com/sem `from()`) + `WorkflowOrder` (Eloquent model usando o trait).
- **Testes Pest 3** (Orchestra Testbench, sqlite in-memory):
  - `Feature/WorkflowServiceProviderTest` (2): provider boota + config carregado com defaults.
  - `Feature/HasWorkflowTest` (6): metadata da state atual + null gracioso (campo vazio + state não registrada) + transições filtradas por `from()` + transições sempre-abertas mesmo com state null.
  - `Unit/WorkflowDefinitionTest` (7): build fluent + rejeição de field vazio + lookup de metadata + fallbacks de label/color/icon + `toArray()` + rejeição de chaves não-string + rejeição de transitions inválidas.
  - **Total: 15 testes** (assumindo execução pós `composer install`).

**Entregue (WF-004):**

- **`Arqel\Workflow\Events\StateTransitioned`** (final) — evento Laravel disparado *após* uma transição bem-sucedida. Carrega `record: Model`, `from: string`, `to: string`, `userId: ?int`, `context: array<string,mixed>`. Implementa `Dispatchable` + `SerializesModels`. **Não** implementa `ShouldBroadcast` por design — broadcasting é opt-in via listener dedicado, mantendo `arqel/workflow` standalone.
- **`Arqel\Workflow\Concerns\HasWorkflow::transitionTo(string $newState, array $context = [])`** — helper que captura state atual via `resolveStateKey`, delega à API spatie quando `state->transitionTo()` existir, ou faz attribute assignment + save direto. Após mutação, dispara `StateTransitioned` capturando `Auth::id()`. Suprime evento quando `arqel-workflow.audit.enabled === false` ou `audit.log_via !== 'event'` — útil para migrations / seeders.
- **`Arqel\Workflow\Concerns\RecordsStateTransition`** trait opcional para classes spatie `Transition` que prefiram disparar o evento canônico no próprio `handle()`. Exemplo de listener registro em `EventServiceProvider`:
  ```php
  protected $listen = [
      \Arqel\Workflow\Events\StateTransitioned::class => [
          \App\Listeners\LogTransitionToAudit::class,
          \App\Listeners\BroadcastTransition::class,
      ],
  ];
  ```
- **Config bloco `audit`** em `arqel-workflow.php`: `enabled` (env `ARQEL_WORKFLOW_AUDIT_ENABLED`, default `true`) + `log_via` (`'event'|'silent'`, default `'event'`).
- **8 testes Pest** novos em `Feature/StateTransitionedEventTest`: dispatch happy path, context propagation, captura `Auth::id()`, fallback para `null`, audit disabled skip, log_via silent skip, persistência do state após transitionTo, trait `RecordsStateTransition` em fixture spatie-style.

**Entregue (WF-006):**

- **`Arqel\Workflow\Authorization\TransitionAuthorizer`** (final readonly) — autorização central. 3 camadas em ordem de precedência: (1) `authorizeFor(?Authenticatable $user, mixed $record): bool` na transition class (estático ou instância — preferido); (2) Gate `transition-{fromSlug}-to-{toSlug}`; (3) **deny by default** — flag opt-out via `arqel-workflow.authorization.deny_when_undefined => false` (preserva legado WF-003). Exceções no `authorizeFor` degradam para `false` (fail closed).
- Helper público `TransitionAuthorizer::slugifyState(string $stateClassOrKey): string` — kebab-case do segmento final do FQCN, sufixos `State` removidos. Útil para gerar Gate names manualmente.
- **`StateTransitionField::resolveAvailableTransitions()`** atualizado para delegar ao `TransitionAuthorizer`. Payload `transitions[].authorized` reflete o resultado das 3 camadas.
- **Breaking change vs WF-003**: `StateTransitionField` original retornava `true` quando nenhuma Gate estava registrada. Agora deny-by-default — defina a flag `deny_when_undefined => false` para opt-out.
- **10 Pest tests** novos (8 unit + 2 feature integration).

**Entregue (WF-007):**

- **Migration `2026_05_01_000000_create_arqel_state_transitions_table`** — cria a tabela append-only `arqel_state_transitions` com `morphs('model')`, `from_state` (nullable), `to_state`, `transitioned_by_user_id` (indexed), `metadata` (JSON), `created_at` com `useCurrent()`. Sem `updated_at` por design (audit é append-only).
- **`Arqel\Workflow\Models\StateTransition`** (final) — Eloquent model com `$timestamps = false`, casts `metadata => array` + `created_at => datetime`. Relacionamentos: `model(): MorphTo` (polimórfico) e `user(): ?BelongsTo` (defensive — lê `arqel-workflow.user_model` default `App\\Models\\User`, retorna `null` se a classe não existir/não for `Model`).
- **`Arqel\Workflow\Listeners\PersistStateTransitionToHistory`** (final) — listener auto-registado pelo `WorkflowServiceProvider::packageBooted()` via `Event::listen(StateTransitioned::class, ...)`. Persiste cada transição em `arqel_state_transitions` com `model_type`, `model_id`, `from_state` (null se vazio), `to_state`, `transitioned_by_user_id`, `metadata` (null se context vazio). Skip silencioso quando `arqel-workflow.history.enabled === false`. Captura `Throwable` defensivamente — falha de persistência não impede a transição do domínio.
- **`HasWorkflow::stateTransitions()`** — `MorphMany<StateTransition, $this>` retornando o histórico do record ordenado por `created_at desc, id desc` (sqlite second-level ties resolvidos pelo id auto-increment). Uso direto em UIs de timeline: `$order->stateTransitions()->latest()->first()`.
- **`StateTransitionField::resolveHistory()`** — agora retorna entries reais filtradas por `(model_type, model_id)` (limit configurável via `arqel-workflow.history.limit`, default 50). Cada row: `{from, to, at (ISO 8601), by, metadata}`. Best-effort: captura `Throwable` e retorna `[]` se a tabela não foi migrada.
- **Config bloco `history`** em `arqel-workflow.php`: `enabled` (env `ARQEL_WORKFLOW_HISTORY_ENABLED`, default `true`) + `limit` (env `ARQEL_WORKFLOW_HISTORY_LIMIT`, default `50`).
- **`tests/TestCase.php`** carrega migrações do pacote via `defineDatabaseMigrations()` (pattern alinhado com `arqel/ai`).
- **7 testes Pest** novos em `Feature/StateTransitionHistoryTest`: persistência (model_type/model_id/from/to/userId/metadata) + JSON cast + relacionamento `stateTransitions()` + skip quando `history.enabled=false` + 2 transições sequenciais em ordem desc + `StateTransitionField::resolveHistory()` non-empty + `[]` quando record não está bound. **Total acumulado: 46 testes**.

Exemplo de consumo:

```php
// Latest transition de um order
$last = $order->stateTransitions()->first();
// StateTransition { from_state: 'Pending', to_state: 'Paid', metadata: [...], created_at }

// Timeline completo
foreach ($order->stateTransitions as $entry) {
    echo "{$entry->from_state} → {$entry->to_state} at {$entry->created_at}\n";
}

// Disabled em jobs / seeders
config()->set('arqel-workflow.history.enabled', false);
$order->transitionTo(NewState::class); // não grava em arqel_state_transitions
```

**Por chegar (WF-008+ — diferidos):**

- `Http\Controllers\TransitionController` — endpoint `POST /admin/{resource}/{record}/transition/{transition}` que valida + dispara a transição (depende do registro `arqel/core` Resource + auth).
- `WorkflowVisualizer` React component — diagrama interativo do workflow (states + transitions) consumindo `WorkflowDefinition::toArray()`.
- Integração canônica com `spatie/laravel-model-states` — guard helpers + casts + transition events; o suggest entra em `require` quando o usuário opta in.

## Conventions

- `declare(strict_types=1)` obrigatório em todos os arquivos PHP.
- `WorkflowDefinition` é `final`; `HasWorkflow` é trait (consumida por user-land Eloquent models, portanto não pode ser final).
- **Sem hard dep em `spatie/laravel-model-states`** — design intent. O pacote roda standalone com qualquer representação de state (FQCN, enum, string slug). A dep só é declarada como `suggest:` no `composer.json`.
- Resolução de chave de state (`HasWorkflow::resolveStateKey`):
  - objeto → `::class` (alinhado com spatie/laravel-model-states que indexa por FQCN)
  - `BackedEnum` → `value`
  - string não-vazia → ela mesma
  - resto → `null` (graceful)
- Transições sem `from()` são tratadas como **sempre disponíveis** (pattern "any-to-X", e.g. `Cancel`).
- Labels ausentes em `states()` são derivadas pela última parte do FQCN, com PascalCase splitado por espaço (`PendingPayment` → `Pending Payment`).

## Examples

Definição declarativa em um Eloquent Model:

```php
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    use HasWorkflow;

    protected $casts = [
        'order_state' => OrderState::class, // spatie state cast (opcional)
    ];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                OrderState\Pending::class   => ['label' => 'Pending',   'color' => 'warning',     'icon' => 'clock'],
                OrderState\Paid::class      => ['label' => 'Paid',      'color' => 'info',        'icon' => 'credit-card'],
                OrderState\Shipped::class   => ['label' => 'Shipped',   'color' => 'primary',     'icon' => 'truck'],
                OrderState\Delivered::class => ['label' => 'Delivered', 'color' => 'success',     'icon' => 'check-circle'],
                OrderState\Cancelled::class => ['label' => 'Cancelled', 'color' => 'destructive', 'icon' => 'x-circle'],
            ])
            ->transitions([
                Transitions\PendingToPaid::class,
                Transitions\PaidToShipped::class,
                Transitions\ShippedToDelivered::class,
                Transitions\AnyToCancelled::class,
            ]);
    }
}
```

Consumo em controller / Inertia:

```php
$order = Order::find($id);

$current = $order->getCurrentStateMetadata();
// ['label' => 'Paid', 'color' => 'info', 'icon' => 'credit-card']

$available = $order->getAvailableTransitions();
// [Transitions\PaidToShipped::class, Transitions\AnyToCancelled::class]

$payload = $order->arqelWorkflow()->toArray();
// {field, states, transitions} — pronto para Inertia props
```

## Anti-patterns

- Adicionar `spatie/laravel-model-states` em `require` do `arqel/workflow` — quebra o design *suggest-only*. Se você precisa do cast spatie, declare em `require` da sua **app**, não do pacote.
- Chamar métodos específicos da spatie (`->canTransitionTo(...)`, `transitionTo(...)`) dentro do trait — quebra o duck-typing. Esse acoplamento entra apenas em WF-003+ (cross-package, opt-in).
- Usar `getAvailableTransitions()` como autorização — é metadata para UI. Authorization real fica nos `Transition::class::canTransition()` da spatie ou em `Gate::allows()` no controller (ADR-017).
- Registrar a mesma state em chaves diferentes (`OrderState\Paid::class` + `'paid'`) — `getCurrentStateMetadata()` resolve para uma chave canônica; entradas duplicadas vão silently miss.
- `WorkflowDefinition::make('')` ou state map com chave vazia — lançam `InvalidArgumentException`.

## Related

- Source: [`packages/workflow/src/`](./src/)
- Testes: [`packages/workflow/tests/`](./tests/)
- Tickets: [`PLANNING/10-fase-3-avancadas.md`](../../PLANNING/10-fase-3-avancadas.md) §WF-001..006
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Workflow
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client
- Externos: [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) (suggest — integração canônica, opt-in)
