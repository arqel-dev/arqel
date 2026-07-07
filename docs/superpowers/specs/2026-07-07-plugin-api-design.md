# Plugin API no Panel (milestone 0.19) — Design Spec

> **Status:** aprovado (brainstorming 2026-07-07). Fonte para o plano de implementação.
> **Milestone:** 0.19 — abre a extensibilidade in-code (lacuna competitiva #5 vs Filament/Nova).
> **Base:** origin/main `68601bb` (após #356 Relation Managers + #358 + #360).

## Objetivo

Entregar um **Plugin API in-code** no estilo Filament: um contrato `Plugin` (`getId`/`register(Panel)`/`boot(Panel)`) que um pacote implementa para injetar resources/widgets/nav/middleware num Panel programaticamente, registrado via `Panel::plugin(MyPlugin::make())`. Hoje só um `ServiceProvider` do app pode registrar conteúdo num Panel; não há indireção de plugin, e o marketplace `Plugin` (Eloquent) trata apenas de **distribuição** (catálogo/instalação), não de registro runtime.

**Escopo decidido (brainstorming):**
1. **Superfície existente** — o plugin reusa `resources()`/`widgets()`/`navigationGroups()`/`middleware()` que o `Panel` **já** expõe. Zero features novas de UI.
2. Fechar o gap **`widgets()` → `DashboardRegistry`** (hoje desconectado — `Panel::widgets()` armazena mas nada sincroniza, diferente de `resources()`).
3. Contrato em **`packages/core`** (marketplace não pode ser dependência de core; a seta é `marketplace → core`).
4. Lifecycle **two-phase**: `register(Panel)` eager na cadeia fluente; `boot(Panel)` no `$this->app->booted()` **antes** do sync.

## Contexto factual (exploração do código)

Reutilizável como está:
- `Arqel\Core\Panel\Panel` — builder fluente mutável; já tem `resources()`, `widgets()`, `navigationGroups()`, `middleware()`, `getResources()`, `getWidgets()`.
- `Arqel\Core\Panel\PanelRegistry` — singleton keyed por id; `all()` itera todos os panels.
- `Arqel\Core\ArqelServiceProvider::packageBooted()` — difere sync/rotas para `$this->app->booted()` (roda após todos os providers): `discoverResourcesIfEnabled` → `syncPanelResourcesIntoRegistry` → `electDefaultCurrentPanel` → `registerResourceRoutes`.
- `Arqel\Core\Resources\ResourceRegistry` — sink de resources (sync via `syncPanelResourcesIntoRegistry`).
- `Arqel\Widgets\DashboardRegistry` (pacote opcional `arqel-dev/widgets`) — sink de widgets; `register()` recebe uma **instância** (`MainDashboard::make()`), idempotente.
- Precedente de teste: `PanelToRegistrySyncTest` invoca métodos privados do provider via `ReflectionMethod::setAccessible(true)` e assere contra os registries.
- Precedente de desacoplamento: `core` não depende de `table`/`form` (circular) → duck-typing + `class_exists`/string. Mesmo padrão vale p/ `widgets`.

A construir (gaps): o contrato `Plugin`, `Panel::plugin()/plugins()/getPlugins()/getPlugin()`, os métodos `bootPanelPlugins()` e `syncPanelWidgetsIntoRegistry()` no provider, e 1 plugin de dogfood no showcase.

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
- `register()` dispara na **ordem de inserção**; `boot()` (Seção 3) na mesma ordem.
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

Ordem no `booted()`: `discoverResourcesIfEnabled` → **`bootPanelPlugins`** → `syncPanelResourcesIntoRegistry` → `syncPanelWidgetsIntoRegistry` (Seção 3) → `electDefaultCurrentPanel` → `registerResourceRoutes`.

---

## Seção 3 — Bridge widgets() → DashboardRegistry (o gap desconectado)

Hoje `Panel::widgets()` **armazena** as classes mas nada as sincroniza com o `DashboardRegistry` (diferente de `resources()`, que é sincronizado por `syncPanelResourcesIntoRegistry`). Para um Plugin registrar widgets e eles realmente aparecerem no dashboard, esse gap precisa fechar.

**Correção:** novo passo de sync no `booted()`, análogo ao de resources:

```php
private function syncPanelWidgetsIntoRegistry(): void
{
    if (!class_exists(\Arqel\Widgets\DashboardRegistry::class)) {
        return; // arqel-dev/widgets é pacote opcional → no-op se ausente
    }
    $registry = $this->app->make(\Arqel\Widgets\DashboardRegistry::class);
    foreach ($this->panelRegistry->all() as $panel) {
        foreach ($panel->getWidgets() as $widget) {
            // $widget é class-string; DashboardRegistry::register() espera instância
            $registry->register($widget::make());  // idempotente (mesmo widget 2x = 1)
        }
    }
}
```

Cuidados factuais:
- **`core` NÃO pode depender de `widgets`** (mesmo padrão de table/form) → o bridge é **guard-ed por `class_exists`** e resolvido via container/string (`\Arqel\Widgets\DashboardRegistry::class` referenciado como string FQCN, nunca `use` no topo). Se `arqel-dev/widgets` não estiver instalado, o passo é no-op silencioso.
- `DashboardRegistry::register()` recebe uma **instância** (`MainDashboard::make()`), não a classe — o bridge instancia via `$widget::make()` (convenção de factory dos widgets). Idempotente.
- Assumir a existência de `Widget::make()` é uma dependência de convenção verificável no plano; se a factory divergir, o plano ajusta a instanciação (via container `$this->app->make($widget)`).

Ordem final no `booted()`: `discover` → `bootPanelPlugins` → `syncPanelResources` → **`syncPanelWidgets`** → `electDefault` → `registerRoutes`.

---

## Seção 4 — Testes, escopo & autorização

**Testes** (ADR-008; ≥90% PHP):

*PHP (Pest, `core`):*
- Unit `Panel`:
  - `plugin()` chama `register()` eager (spy/fake plugin) e muta o Panel — resource do plugin aparece em `getResources()`, widget em `getWidgets()`;
  - id duplicado (dois plugins mesmo `getId()`) → **último vence** (`getPlugins()` tem 1 entrada, a segunda);
  - `plugins([...])` itera e registra todos, na ordem;
  - `getPlugin(id)` retorna o plugin / `null` se ausente.
- Feature (reflection-invoke dos métodos privados do provider, padrão `PanelToRegistrySyncTest`):
  - `bootPanelPlugins` chama `boot()` de cada plugin, **na ordem de inserção**, e roda **antes** do sync;
  - resource adicionado **dentro de `boot()`** de um plugin **vira rota** (assert em `ResourceRegistry`/route-slugs após a sequência completa) — prova da ordem correta;
  - `syncPanelWidgetsIntoRegistry` registra os widgets do panel no `DashboardRegistry`; **no-op** quando `class_exists` é falso (guard);
  - end-to-end: um `FixturePlugin` que em `register()` faz `$panel->resources([X])->widgets([Y])` → após o `booted()` completo, `X` está no `ResourceRegistry` e `Y` no `DashboardRegistry`.

*JS (Vitest):* **nenhum componente React novo** (superfície existente) → sem testes novos. Widgets já renderizam via `DashboardRegistry` no dashboard existente.

*E2E (Playwright, dogfood 8090):* leve/opcional — empacotar 1 resource já existente do showcase como um `ShowcasePlugin` e validar no dogfood que o resource aparece na navegação. **Decisão de custo-benefício na fase de plano**: a prova server-side ("resource adicionado em boot vira rota" + "widget chega ao registry") já é forte; o E2E só confirma que o registro via plugin é indistinguível do registro direto na UI. Se incluído, assere por **conteúdo** (item de nav específico aparece), não count.

**Autorização:** nenhuma superfície de autorização nova. Resources/widgets registrados via plugin passam pela **mesma** autorização já existente (Policies via `ResourceController`, `HasAuthorization`) — o plugin é só um canal de registro, não um bypass. Nota no plano: um plugin de terceiros pode registrar um resource cujo model o app não pretendia expor — isso é responsabilidade do app que instala o plugin (mesmo modelo de confiança de qualquer pacote Composer), não um novo vetor introduzido aqui.

**Escopo — pacotes afetados:** só **`core`** (contrato `Plugin` + trait opcional `make()` + `Panel::plugin/plugins/getPlugins/getPlugin` + `bootPanelPlugins` + `syncPanelWidgetsIntoRegistry` no provider) e, no **showcase**, 1 plugin de dogfood. **Não é pacote novo** → sem os 4 pontos de registro de pacote. Bridge de widgets guard-ed por `class_exists` → **zero dependência nova** (não adiciona `arqel-dev/widgets` como require de `core`).

**Fora de escopo (YAGNI → 0.19b ou depois):**
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
| `ArqelServiceProvider::syncPanelWidgetsIntoRegistry` | Sincroniza `Panel::getWidgets()` → DashboardRegistry (guard-ed) | PanelRegistry, DashboardRegistry (opcional, via string+class_exists) |
| `ShowcasePlugin` (dogfood) | Empacota 1 resource como plugin | Plugin, showcase resource |

Cada unidade tem propósito único e interface bem definida; testável isoladamente. O bridge de widgets é a única com dependência opcional (guard-ed), preservando o desacoplamento de `core`.
