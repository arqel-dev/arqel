# Plugin API no Panel (milestone 0.19) — Design Spec

> **Status:** aprovado (brainstorming 2026-07-07). Fonte para o plano de implementação.
> **Milestone:** 0.19 — abre a extensibilidade in-code (lacuna competitiva #5 vs Filament/Nova).
> **Base:** origin/main `68601bb` (após #356 Relation Managers + #358 + #360).

## Objetivo

Entregar um **Plugin API in-code** no estilo Filament: um contrato `Plugin` (`getId`/`register(Panel)`/`boot(Panel)`) que um pacote implementa para injetar resources/nav/middleware num Panel programaticamente, registrado via `Panel::plugin(MyPlugin::make())`. Hoje só um `ServiceProvider` do app pode registrar conteúdo num Panel; não há indireção de plugin, e o marketplace `Plugin` (Eloquent) trata apenas de **distribuição** (catálogo/instalação), não de registro runtime.

**Escopo decidido (brainstorming):**
1. **Superfície existente** — o plugin reusa `resources()`/`navigationGroups()`/`middleware()` que o `Panel` **já** expõe e que **já têm consumidor** no boot pipeline. Zero features novas de UI.
2. Contrato em **`packages/core`** (marketplace não pode ser dependência de core; a seta é `marketplace → core`).
3. Lifecycle **two-phase**: `register(Panel)` eager na cadeia fluente; `boot(Panel)` no `$this->app->booted()` **antes** do sync.

> **Revisão de escopo (2026-07-07, pós-exploração do plano):** a Seção 3 original (bridge `widgets()`→`DashboardRegistry`) foi **removida**. A exploração revelou que `Panel::widgets()` guarda `class-string<Widget>` de widgets **individuais**, enquanto `DashboardRegistry::register()` só aceita um `Dashboard` (container), e as factories exigem argumentos (`Dashboard::make(id,label)`, `CustomWidget::make(name,component)`) — não o `$widget::make()` sem args assumido. Além disso, `Panel::getWidgets()` é hoje um **campo órfão** (sem consumidor em nenhum lugar do código). Reconciliar Panel↔Dashboard é uma feature de widgets por si só → **0.19b**. O Plugin API entrega completo o diferencial (extensibilidade in-code de resources/nav/middleware) sem o bridge.

## Contexto factual (exploração do código)

Reutilizável como está:
- `Arqel\Core\Panel\Panel` — builder fluente mutável **`final`**; já tem `resources(array)`, `navigationGroups(array)`, `middleware(array)`, `getResources()`. `resources()` faz `array_values($resources)`.
- `Arqel\Core\Panel\PanelRegistry` — singleton keyed por id; `all()` itera todos os panels; `clear()` reseta (usado em testes).
- `Arqel\Core\ArqelServiceProvider` — em `packageBooted()` difere sync/rotas para `$this->app->booted()` (roda após todos os providers), com métodos `protected`: `discoverResourcesIfEnabled()` → `syncPanelResourcesIntoRegistry()` → `electDefaultCurrentPanel()` → `registerResourceRoutes()`.
- `Arqel\Core\Resources\ResourceRegistry` — sink de resources; `register(class-string)`, `has(class-string)`, `clear()`. Sync via `syncPanelResourcesIntoRegistry` (skip de não-string/classe-inexistente/já-registrado).
- Precedente de teste: `PanelToRegistrySyncTest` (`packages/core/tests/Feature/`) invoca métodos do provider via `app()->getProvider(ArqelServiceProvider::class)` + `ReflectionClass::getMethod(...)->setAccessible(true)->invoke($provider)`, com `beforeEach` limpando ambos os registries.
- Precedente de desacoplamento: `core` não depende de `table`/`form` (circular) → duck-typing + `class_exists`/string.

A construir (gaps): o contrato `Plugin`, o trait opcional `make()`, `Panel::plugin()/plugins()/getPlugins()/getPlugin()`, o método `bootPanelPlugins()` no provider (invocado no `booted()` **antes** do sync), e 1 plugin de dogfood no showcase. **Sem** bridge de widgets (ver revisão de escopo acima).

---

## Seção 1 — Contrato & lifecycle (PHP, `packages/core`)

**Novo contrato** `Arqel\Core\Contracts\Plugin`:

```php
interface Plugin
{
    public function getId(): string;              // id estável, único por panel
    public function register(Panel $panel): void; // muta o Panel (resources/widgets/nav/middleware)
    public function boot(Panel $panel): void;     // efeitos após todos registrarem
}
```

Vive em `core` porque `core` não pode depender de `marketplace` — assim qualquer pacote implementa o contrato com só a dependência `core`. O `Plugin` Eloquent do marketplace (catálogo/distribuição) é um conceito distinto e **não muda**.

Convenção Filament-style **opcional**: um trait `Concerns\CreatesPlugin` (ou base) provendo `public static function make(): static { return new static(); }` para `Panel::plugin(MyPlugin::make())`. O contrato é o mínimo; o trait é açúcar (não obrigatório para implementar `Plugin`).

**Lifecycle two-phase:**
- `register(Panel)` roda **eager**, no momento em que `->plugin($p)` é chamado na cadeia fluente (dentro do `boot()` do ServiceProvider do app) — o plugin muta o Panel imediatamente.
- `boot(Panel)` roda no `$this->app->booted()` do `ArqelServiceProvider`, **antes** de `syncPanelResourcesIntoRegistry()` — assim resources/widgets adicionados em `boot()` ainda entram no sync e viram rota (espelha Filament, onde `boot` ainda pode registrar).

---

## Seção 2 — Panel::plugin() + estado (PHP)

Adições ao `Panel` (aditivas, não-breaking):

```php
/** @var array<string, Plugin> keyed por getId() */
private array $plugins = [];

public function plugin(Plugin $plugin): self
{
    $this->plugins[$plugin->getId()] = $plugin;  // último vence (override previsível)
    $plugin->register($this);                     // register eager
    return $this;
}

/** @param array<Plugin> $plugins */
public function plugins(array $plugins): self
{
    foreach ($plugins as $p) { $this->plugin($p); }
    return $this;
}

/** @return array<string, Plugin> */
public function getPlugins(): array { return $this->plugins; }
public function getPlugin(string $id): ?Plugin { return $this->plugins[$id] ?? null; }
```

- Map keyed por `getId()` → registrar o mesmo id 2x **substitui** (permite app sobrescrever plugin de terceiros pelo mesmo id; padrão legítimo).
- `register()` dispara na **ordem de inserção**; `boot()` (Seção 3) na mesma ordem. Nota: PHP preserva a ordem de inserção de arrays associativos mesmo com keys string, então iterar `$this->plugins` respeita a ordem de `->plugin()`.
- `getPlugins()`/`getPlugin()` expõem o estado para teste e para o passo de boot no provider.

**Fase boot no ServiceProvider** — novo método privado, invocado no início do `booted()`, **antes** do sync:

```php
private function bootPanelPlugins(): void
{
    foreach ($this->panelRegistry->all() as $panel) {
        foreach ($panel->getPlugins() as $plugin) {
            $plugin->boot($panel);
        }
    }
}
```

**Ordem final no `booted()`:** `discoverResourcesIfEnabled` → **`bootPanelPlugins`** → `syncPanelResourcesIntoRegistry` → `electDefaultCurrentPanel` → `registerResourceRoutes`. O `bootPanelPlugins` roda **antes** de `syncPanelResourcesIntoRegistry` justamente para que qualquer `resources([...])` que um plugin adicione em `boot()` ainda seja copiado ao `ResourceRegistry` e vire rota.

---

## Seção 3 — Testes, escopo & autorização

**Testes** (ADR-008; ≥90% PHP):

*PHP (Pest, `core`):*
- Unit `Panel`:
  - `plugin()` chama `register()` eager (fake plugin) e muta o Panel — resource do plugin aparece em `getResources()`;
  - id duplicado (dois plugins mesmo `getId()`) → **último vence** (`getPlugins()` tem 1 entrada, a segunda);
  - `plugins([...])` itera e registra todos, na ordem de inserção;
  - `getPlugin(id)` retorna o plugin / `null` se ausente; `getPlugins()` keyed por id.
- Feature (reflection-invoke dos métodos do provider, padrão `PanelToRegistrySyncTest`):
  - `bootPanelPlugins` chama `boot()` de cada plugin do panel, **na ordem de inserção**;
  - resource adicionado **dentro de `boot()`** de um plugin **vira rota** — chamar `bootPanelPlugins` e depois `syncPanelResourcesIntoRegistry` (a ordem real do `booted()`) e assertar que o resource está no `ResourceRegistry` — prova da ordem correta;
  - end-to-end: um `FixturePlugin` que em `register()` faz `$panel->resources([X])` → após `bootPanelPlugins` + `syncPanelResourcesIntoRegistry`, `X` está no `ResourceRegistry`.

*JS (Vitest):* **nenhum componente React novo** (superfície existente) → sem testes novos.

*E2E (Playwright, dogfood 8090):* leve/opcional — empacotar 1 resource já existente do showcase como um `ShowcasePlugin` e validar no dogfood que o resource aparece na navegação. **Decisão de custo-benefício na fase de plano**: a prova server-side ("resource adicionado em boot vira rota") já é forte; o E2E só confirma que o registro via plugin é indistinguível do registro direto na UI. Se incluído, assere por **conteúdo** (item de nav específico aparece), não count.

**Autorização:** nenhuma superfície de autorização nova. Resources registrados via plugin passam pela **mesma** autorização já existente (Policies via `ResourceController`, `HasAuthorization`) — o plugin é só um canal de registro, não um bypass. Nota no plano: um plugin de terceiros pode registrar um resource cujo model o app não pretendia expor — isso é responsabilidade do app que instala o plugin (mesmo modelo de confiança de qualquer pacote Composer), não um novo vetor introduzido aqui.

**Escopo — pacotes afetados:** só **`core`** (contrato `Plugin` + trait opcional `make()` + `Panel::plugin/plugins/getPlugins/getPlugin` + `bootPanelPlugins` no provider) e, no **showcase**, 1 plugin de dogfood. **Não é pacote novo** → sem os 4 pontos de registro de pacote. **Zero dependência nova.**

**Fora de escopo (YAGNI → 0.19b ou depois):**
- **Bridge `Panel` → widgets/dashboards** — requer reconciliar `Panel::widgets()` (class-strings de Widget) com `DashboardRegistry` (aceita `Dashboard`), hoje incompatíveis; `Panel::getWidgets()` é campo órfão. Feature de widgets por si só (ver revisão de escopo no topo).
- Panel **Pages** standalone (rota + componente custom fora do CRUD) — conceito não existe hoje.
- **Render hooks / slots** no layout React (Filament `renderHook()`).
- **Nav-items ricos** (route/icon/label) — hoje `navigationGroups` é só labels de grupo.
- **Per-plugin middleware merge** — `middleware()` hoje substitui o array inteiro.
- `PluginConventionValidator` do marketplace cruzar com o `getId()` in-code na submissão.

**Contrato de entrega:** serialização N/A (feature server-side de registro, sem novo payload Inertia). Lifecycle testado ponta-a-ponta (register eager → boot → sync → registries). CI local validado (commitlint scope `core`/`showcase` já na allowlist; header ≤100; biome; typecheck) antes de push.

---

## Unidades e interfaces (isolamento)

| Unidade | Faz | Depende de |
|---|---|---|
| `Contracts\Plugin` (interface) | Define `getId`/`register`/`boot` | Panel (type-hint) |
| `Concerns\CreatesPlugin` (trait, opcional) | Provê `make(): static` | — |
| `Panel::plugin/plugins/getPlugins/getPlugin` | Registra plugins (map por id, register eager) | Plugin |
| `ArqelServiceProvider::bootPanelPlugins` | Dispara `boot()` de cada plugin antes do sync | PanelRegistry, Plugin |
| `ShowcasePlugin` (dogfood) | Empacota 1 resource como plugin | Plugin, showcase resource |

Cada unidade tem propósito único e interface bem definida; testável isoladamente. `core` não ganha nenhuma dependência nova — o Plugin API reusa só a superfície de registro de resources/nav/middleware que já existe e já tem consumidor no boot pipeline.
