# `arqel-dev/workflow` — Referência de API

Namespace `Arqel\Workflow\`. Máquinas de estado para models Eloquent: os estados que um registro pode ocupar, seus metadados de UI (label / color / icon), as transições legais entre eles, autorização central, eventos de auditoria e uma tabela de histórico append-only.

A integração canônica é com [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states), mas o pacote é inteiramente duck-typed — enums PHP, slugs simples em string ou tokens customizados também funcionam. `spatie/laravel-model-states` fica em `suggest:`, nunca em `require`.

## `Arqel\Workflow\WorkflowDefinition` (final)

Builder fluente que descreve um workflow. Factory `WorkflowDefinition::make(string $field): self` — lança exceção quando `$field` está vazio.

| Método | Tipo | Descrição |
|---|---|---|
| `states(array $states)` | `self` | `array<string, {label?, color?, icon?}>`, tipicamente indexado por `class-string<State>` (ou um valor de enum / slug arbitrário). Substitui em vez de mesclar |
| `transitions(array $transitions)` | `self` | `list<class-string>` de classes de transição |
| `getField()` | `string` | A coluna Eloquent que persiste o estado |
| `getStates()` | `array` | |
| `getTransitions()` | `array` | |
| `getStateMetadata(string $stateClass)` | `?array` | `{label, color, icon}` de um estado |
| `toArray()` | `array` | `{field, states, transitions}` — pronto para props do Inertia |

Labels ausentes são derivados do último segmento PascalCase do FQCN (`OrderState\PendingPayment` → `Pending Payment`); um `color`/`icon` ausente cai em `'secondary'` / `'circle'`.

## `Arqel\Workflow\Concerns\HasWorkflow` (trait)

Consumida por models Eloquent do usuário, que precisam declarar `arqelWorkflow(): WorkflowDefinition`.

| Método | Tipo | Descrição |
|---|---|---|
| `getCurrentStateMetadata()` | `?array` | `{label, color, icon}` do estado atual do registro |
| `getAvailableTransitions()` | `array` | Class-strings de transições cujo `from()` inclui o estado atual, mais toda transição sem `from()` (o padrão "any-to-X") |
| `transitionTo(string $newState, array $context = [])` | `void` | Executa a transição e dispara `StateTransitioned` |
| `stateTransitions()` | `MorphMany` | Linhas de histórico, ordenadas por `created_at desc, id desc` |

A resolução da chave de estado é polimórfica: um objeto resolve para `::class`, um `BackedEnum` para o seu `value`, uma string não vazia para ela mesma, qualquer outra coisa para `null`.

`transitionTo()` delega à API do spatie quando o objeto de estado atual expõe o seu próprio `transitionTo()` (deixando os guards do spatie intactos); caso contrário, segue o caminho de fallback — atribuição simples de atributo mais `save()`. **No caminho de fallback, a verificação roda antes de qualquer mutação ou persistência:** quando o model declara transições, `$newState` precisa ser alcançável (alguma transição declarada cujo `from()` inclua o estado atual — ou que não tenha `from()` — e cujo destino corresponda a `$newState`), caso contrário `Exceptions\IllegalTransitionException` é lançada; entre as transições correspondentes, ao menos uma precisa estar autorizada pelo `TransitionAuthorizer`, caso contrário `Exceptions\UnauthorizedTransitionException` é lançada. Models que declaram `transitions([])` permanecem free-form — nenhuma elegibilidade ou autorização é imposta.

O evento é suprimido quando `arqel-workflow.audit.enabled === false` ou `audit.log_via !== 'event'` — útil para migrations e seeders.

## `Arqel\Workflow\Concerns\RecordsStateTransition` (trait)

Helper opcional que permite a uma classe `Transition` do spatie disparar o evento canônico `StateTransitioned` a partir do seu próprio `handle()`. Ela não assume nenhuma API própria do spatie.

## `Arqel\Workflow\Events\StateTransitioned` (final)

Disparado após uma transição bem-sucedida. `Dispatchable` + `SerializesModels`; deliberadamente **não** é `ShouldBroadcast` — o broadcasting é opt-in via um listener dedicado.

```php
public function __construct(
    public readonly Model $record,
    public readonly string $from,
    public readonly string $to,
    public readonly ?int $userId = null,
    public readonly array $context = [],
) {}
```

## `Arqel\Workflow\Authorization\TransitionAuthorizer` (final readonly)

| Método | Tipo | Descrição |
|---|---|---|
| `authorize(string $transitionClass, ?Authenticatable $user, mixed $record)` | `static bool` | Aceita qualquer string — uma classe que não existe retorna `false` |
| `authorizeStates(string $from, string $to, ?Authenticatable $user, mixed $record)` | `static bool` | Decisão indexada pelo par de estados, e não pela classe de transição |
| `slugifyState(string $stateClassOrKey)` | `static string` | kebab-case do segmento final do FQCN, removendo um sufixo `State` (`'PendingPayment'` → `'pending-payment'`, `'PaidState'` → `'paid'`, `''`/`'*'` → `'*'`) |

Três camadas, nesta ordem: (1) `authorizeFor(?Authenticatable, mixed): bool` declarado na transição (estático ou de instância — preferido); (2) um Gate registrado com o nome `transition-{fromSlug}-to-{toSlug}`; (3) **negar por padrão**. A flag de opt-out é `arqel-workflow.authorization.deny_when_undefined => false`. Exceções lançadas dentro de `authorizeFor` degradam para `false` (fail closed).

## `Arqel\Workflow\Support\TransitionTargetResolver` (final)

`TransitionTargetResolver::resolve(string $transition): string` retorna o token de destino de uma classe de transição: o `to()` estático declarado quando presente, senão a convenção `XxxToYyy` (`PendingToPaid` → `Paid`), senão o short name da classe. O mesmo resolver alimenta o caminho de escrita, o authorizer e o payload serializado do field, de modo que o token anunciado por um botão da UI faz round-trip pelas verificações do lado servidor.

## `Arqel\Workflow\Models\StateTransition` (final)

Linha de histórico append-only em `arqel_state_transitions`. `$timestamps = false`; `metadata` com cast para `array`, `created_at` para `datetime`.

| Método | Tipo | Descrição |
|---|---|---|
| `model()` | `MorphTo` | O registro que sofreu a transição |
| `user()` | `?BelongsTo` | Defensivo — lê `arqel-workflow.user_model` (default `App\Models\User`) e retorna `null` quando a classe não está disponível |

A migration `2026_05_01_000000_create_arqel_state_transitions_table` cria `morphs('model')`, `from_state` nullable, `to_state`, `transitioned_by_user_id` indexado, `metadata` JSON e `created_at` com `useCurrent()` (sem `updated_at`). `model_type` é escrito via `getMorphClass()`, então sob `Relation::enforceMorphMap()` a linha armazena o alias.

`Listeners\PersistStateTransitionToHistory` é auto-registrado pelo service provider, é ignorado silenciosamente quando `arqel-workflow.history.enabled === false`, e captura `Throwable` para que uma falha de auditoria nunca bloqueie a transição de domínio.

## `Arqel\Workflow\Fields\StateTransitionField` (final, estende `Arqel\Fields\Field`)

Field ligado ao React que renderiza o estado atual mais seus botões de transição. Factory `StateTransitionField::make(string $name): static`.

| Método | Tipo | Descrição |
|---|---|---|
| `showDescription(bool $show = true)` | `static` | |
| `showHistory(bool $show = true)` | `static` | |
| `transitionsAttribute(string $name = 'state')` | `static` | |
| `record(?Model $record)` | `static` | Vincula o registro cujo estado é renderizado |
| `getRecord()` / `getTransitionsAttribute()` / `isShowingDescription()` / `isShowingHistory()` | getters | |
| `getTypeSpecificProps()` | `array` | Payload serializado mesclado no schema do field |
| `resolveCurrentState()` | `?array` | `{name, label, color, icon}` |
| `resolveAvailableTransitions()` | `array` | `list<{from, to, label, authorized}>` — `authorized` delega ao `TransitionAuthorizer` |
| `resolveHistory()` | `array` | Linhas reais filtradas por `(model_type, model_id)`, com `model_type` indexado por `getMorphClass()`; limite vindo de `arqel-workflow.history.limit` (default 50), best-effort com `Throwable` capturado |

Exatamente uma entrada é emitida por transição aplicável, espelhando `HasWorkflow::getAvailableTransitions()`: uma transição cujo `from()` lista vários estados produz um **único** botão, carregando o estado atual do registro como `from`. Transições cujo `from()` exclui o estado atual são filtradas; transições sem `from()` sempre aparecem.

## Filtros

### `Arqel\Workflow\Filters\StateFilter` (final readonly)

Filtro autônomo que deriva suas opções da `WorkflowDefinition` do model. O construtor `(string $field, string $modelClass)` valida que o field não está vazio, que a classe existe e que a classe usa `HasWorkflow` — lançando `InvalidArgumentException` caso contrário. Factory `StateFilter::make(string $field, string $modelClass): self`.

| Método | Tipo | Descrição |
|---|---|---|
| `toArray()` | `array` | `{field, type: 'state', label, options}` |
| `optionsArray()` | `array` | `array<string, {value, label, color, icon}>` |
| `apply(Builder $query, mixed $value)` | `void` | String → `where`; array → `whereIn` (valores não-string / vazios descartados); `null` ou vazio → no-op |

### `Arqel\Workflow\Filters\StateFilterFactory` (final)

`StateFilterFactory::forResource(string $modelClass, ?string $field = null): StateFilter` — resolve o field a partir de `arqelWorkflow()->getField()` quando omitido.

Não há dependência rígida de `arqel-dev/table`; o código do usuário pluga o `StateFilter` em `Table::filters([...])`.

## Exceções

| Class | Factory |
|---|---|
| `Exceptions\IllegalTransitionException` (final, estende `RuntimeException`) | `IllegalTransitionException::for(string $from, string $to): self` |
| `Exceptions\UnauthorizedTransitionException` (final, estende `RuntimeException`) | `UnauthorizedTransitionException::for(string $to): self` |

## Integração com realtime

`arqel-dev/workflow` permanece autônomo — nunca importa nada de `arqel-dev/realtime`. Quando `arqel-dev/realtime` **está** instalado, o service provider dele registra `Arqel\Realtime\Workflow\BroadcastStateTransitionListener` para `Events\StateTransitioned`, transformando cada transição em um broadcast de `Arqel\Realtime\Events\ResourceUpdated` nos canais `arqel.{slug}` e `arqel.{slug}.{id}` do registro. Desative globalmente com `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` ou `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)`.

## HTTP

O pacote registra **nenhuma rota** — ainda não existe um controller de transição. As aplicações fornecem sua própria rota, que valida a entrada e chama `HasWorkflow::transitionTo()`. Um endpoint canônico `POST /admin/{resource}/{record}/transition/{transition}` é trabalho futuro.

## Exemplo

```php
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;

final class Order extends Model
{
    use HasWorkflow;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                OrderState\Pending::class   => ['label' => 'Pending',   'color' => 'warning',     'icon' => 'clock'],
                OrderState\Paid::class      => ['label' => 'Paid',      'color' => 'info',        'icon' => 'credit-card'],
                OrderState\Shipped::class   => ['label' => 'Shipped',   'color' => 'primary',     'icon' => 'truck'],
                OrderState\Cancelled::class => ['label' => 'Cancelled', 'color' => 'destructive', 'icon' => 'x-circle'],
            ])
            ->transitions([
                Transitions\PendingToPaid::class,
                Transitions\PaidToShipped::class,
                Transitions\AnyToCancelled::class,
            ]);
    }
}
```

```php
final class PaidToShipped
{
    /** @return list<class-string> */
    public static function from(): array
    {
        return [OrderState\Paid::class];
    }

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user !== null && $user->can('ship-orders', $record);
    }
}
```

```php
use Arqel\Workflow\Filters\StateFilter;

Table::make()->filters([
    StateFilter::make('order_state', Order::class),
]);
```

## Relacionados

- SKILL: [`packages/workflow/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/SKILL.md)
- Código-fonte: [`packages/workflow/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/src/)
