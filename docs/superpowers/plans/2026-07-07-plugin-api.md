# Plugin API (milestone 0.19) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar um Plugin API in-code no `arqel-dev/core`: um contrato `Plugin` (`getId`/`register`/`boot`) que um pacote implementa para injetar resources/nav/middleware num `Panel` via `Panel::plugin(MyPlugin::make())`, com lifecycle two-phase (register eager, boot antes do sync).

**Architecture:** Interface `Arqel\Core\Contracts\Plugin` + trait opcional `Arqel\Core\Panel\Concerns\CreatesPlugin` (açúcar `make()`). `Panel` ganha `plugin()/plugins()/getPlugins()/getPlugin()` (map keyed por `getId()`, register eager, último-vence). `ArqelServiceProvider` ganha `bootPanelPlugins()` invocado no `$this->app->booted()` **antes** de `syncPanelResourcesIntoRegistry()`. Um `ShowcasePlugin` no app showcase valida o dogfood.

**Tech Stack:** PHP 8.3+, Laravel 12+, Pest 3. Sem React, sem dependência nova.

## Global Constraints

- `declare(strict_types=1);` em todo arquivo PHP novo.
- Código em inglês; docs/comentários humanos em PT-BR quando houver.
- `final` por default; exceção: a interface `Plugin` e o trait `CreatesPlugin` (pontos de extensão intencionais — não são `final`).
- `core` NÃO pode depender de `arqel-dev/table`, `form`, `widgets`, nem `marketplace`. O contrato `Plugin` vive em `core`.
- Testes obrigatórios (ADR-008), coverage core PHP ≥90%.
- Commits: Conventional Commits + DCO `--signoff`. Scope `core` (na allowlist do commitlint) e `showcase` (na allowlist). Subject ≤100 chars. Referência ao milestone 0.19 no body.
- Pre-commit hook (husky/lint-staged) não roda no worktree sem `node_modules` → usar `git commit --no-verify` para commits só-PHP/só-doc neste worktree; o CI valida.
- Rodar Pest do pacote core: `vendor/bin/pest --filter=<Nome>` a partir de `packages/core` (ou via testbench do pacote). O runner exato é confirmado no Step 0 da Task 1.

---

## File Structure

- `packages/core/src/Contracts/Plugin.php` — **novo** — a interface do contrato.
- `packages/core/src/Panel/Concerns/CreatesPlugin.php` — **novo** — trait opcional com `make(): static`.
- `packages/core/src/Panel/Panel.php` — **modificar** — adicionar `$plugins`, `plugin()`, `plugins()`, `getPlugins()`, `getPlugin()`.
- `packages/core/src/ArqelServiceProvider.php` — **modificar** — adicionar `bootPanelPlugins()` e chamá-lo no `booted()` antes do sync.
- `packages/core/tests/Fixtures/Plugins/FixturePlugin.php` — **novo** — plugin de teste que registra um resource em `register()`.
- `packages/core/tests/Fixtures/Plugins/BootRegisteringPlugin.php` — **novo** — plugin que adiciona resource em `boot()` (prova a ordem).
- `packages/core/tests/Unit/PanelPluginTest.php` — **novo** — unit do `Panel`.
- `packages/core/tests/Feature/PanelPluginBootTest.php` — **novo** — feature do lifecycle no provider.
- `apps/showcase/app/Arqel/Plugins/ShowcasePlugin.php` — **novo** — dogfood; empacota um resource existente.
- `apps/showcase/app/Providers/ArqelServiceProvider.php` — **modificar** — registrar `->plugin(ShowcasePlugin::make())`.

---

## Task 1: Contrato `Plugin` + trait `CreatesPlugin`

**Files:**
- Create: `packages/core/src/Contracts/Plugin.php`
- Create: `packages/core/src/Panel/Concerns/CreatesPlugin.php`
- Test: `packages/core/tests/Unit/PanelPluginTest.php` (criado aqui, expandido nas tasks seguintes)
- Test fixture: `packages/core/tests/Fixtures/Plugins/FixturePlugin.php`

**Interfaces:**
- Produces:
  - `interface Arqel\Core\Contracts\Plugin { public function getId(): string; public function register(\Arqel\Core\Panel\Panel $panel): void; public function boot(\Arqel\Core\Panel\Panel $panel): void; }`
  - `trait Arqel\Core\Panel\Concerns\CreatesPlugin { public static function make(): static; }`
  - `class Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin implements Plugin` — usa `CreatesPlugin`; `getId()` retorna `'fixture'`; `register(Panel)` faz `$panel->resources([PostResource::class])`; `boot(Panel)` no-op; expõe `public bool $registered = false; public bool $booted = false;` setados nos respectivos métodos.

- [ ] **Step 0: Confirmar o runner de testes do core**

Run: `ls packages/core/composer.json packages/core/phpunit.xml* packages/core/tests/Pest.php 2>/dev/null; grep -m1 -E 'pest|phpunit' packages/core/composer.json`
Expected: identifica se os testes rodam via `vendor/bin/pest` (raiz do monorepo) ou dentro de `packages/core`. Anote o comando de teste. (Nas etapas abaixo assume-se `vendor/bin/pest --filter=X` a partir da raiz do monorepo; ajuste o diretório se o Step 0 indicar o contrário.)

- [ ] **Step 1: Escrever o teste que falha (contrato + trait existem e casam)**

Create `packages/core/tests/Fixtures/Plugins/FixturePlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Plugins;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;

final class FixturePlugin implements Plugin
{
    use CreatesPlugin;

    public bool $registered = false;

    public bool $booted = false;

    public function getId(): string
    {
        return 'fixture';
    }

    public function register(Panel $panel): void
    {
        $this->registered = true;
        $panel->resources([PostResource::class]);
    }

    public function boot(Panel $panel): void
    {
        $this->booted = true;
    }
}
```

Create `packages/core/tests/Unit/PanelPluginTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin;

it('builds a plugin via the CreatesPlugin make() helper', function (): void {
    $plugin = FixturePlugin::make();

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->getId())->toBe('fixture');
});
```

- [ ] **Step 2: Rodar o teste e ver falhar**

Run: `vendor/bin/pest --filter='builds a plugin via the CreatesPlugin'`
Expected: FAIL — `Interface "Arqel\Core\Contracts\Plugin" not found` (ou classe do trait não encontrada).

- [ ] **Step 3: Criar a interface**

Create `packages/core/src/Contracts/Plugin.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

use Arqel\Core\Panel\Panel;

/**
 * Contrato de um plugin in-code do Arqel.
 *
 * Um plugin injeta conteúdo num Panel programaticamente. `register()`
 * roda eager (no momento em que `Panel::plugin()` é chamado, dentro do
 * boot do ServiceProvider do app). `boot()` roda depois, no
 * `$this->app->booted()` do ArqelServiceProvider, ANTES do sync de
 * resources — então resources adicionados em `boot()` ainda viram rota.
 */
interface Plugin
{
    /** Id estável e único por panel (registrar 2x o mesmo id substitui). */
    public function getId(): string;

    /** Muta o Panel (resources/navigationGroups/middleware). Roda eager. */
    public function register(Panel $panel): void;

    /** Efeitos após todos os plugins registrarem. Roda antes do sync. */
    public function boot(Panel $panel): void;
}
```

- [ ] **Step 4: Criar o trait**

Create `packages/core/src/Panel/Concerns/CreatesPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Panel\Concerns;

/**
 * Açúcar opcional para plugins: `MyPlugin::make()`.
 *
 * Não é obrigatório para implementar o contrato Plugin — é apenas
 * conveniência para a cadeia fluente `Panel::plugin(MyPlugin::make())`.
 */
trait CreatesPlugin
{
    public static function make(): static
    {
        return new static();
    }
}
```

- [ ] **Step 5: Rodar o teste e ver passar**

Run: `vendor/bin/pest --filter='builds a plugin via the CreatesPlugin'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Contracts/Plugin.php packages/core/src/Panel/Concerns/CreatesPlugin.php packages/core/tests/Fixtures/Plugins/FixturePlugin.php packages/core/tests/Unit/PanelPluginTest.php
git commit --signoff --no-verify -m "feat(core): add Plugin contract and CreatesPlugin trait" -m "Milestone 0.19 Plugin API from docs/superpowers/specs/2026-07-07-plugin-api-design.md"
```

---

## Task 2: `Panel::plugin()` + register eager + estado

**Files:**
- Modify: `packages/core/src/Panel/Panel.php` (adicionar campo + 4 métodos)
- Test: `packages/core/tests/Unit/PanelPluginTest.php` (expandir)

**Interfaces:**
- Consumes: `Arqel\Core\Contracts\Plugin` (Task 1); `FixturePlugin` (Task 1).
- Produces em `Panel`:
  - `public function plugin(\Arqel\Core\Contracts\Plugin $plugin): self` — guarda em `$this->plugins[$plugin->getId()]` e chama `$plugin->register($this)`.
  - `public function plugins(array $plugins): self` — itera `plugin()`.
  - `public function getPlugins(): array` — `array<string, Plugin>` keyed por id.
  - `public function getPlugin(string $id): ?\Arqel\Core\Contracts\Plugin`.

- [ ] **Step 1: Escrever os testes que falham**

Append em `packages/core/tests/Unit/PanelPluginTest.php`:

```php
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;

it('calls register() eagerly and mutates the panel', function (): void {
    $panel = new Panel('admin');
    $plugin = FixturePlugin::make();

    $panel->plugin($plugin);

    expect($plugin->registered)->toBeTrue()
        ->and($panel->getResources())->toContain(PostResource::class)
        ->and($panel->getPlugin('fixture'))->toBe($plugin);
});

it('keys plugins by id so the last registration wins', function (): void {
    $panel = new Panel('admin');
    $first = FixturePlugin::make();
    $second = FixturePlugin::make();

    $panel->plugin($first)->plugin($second);

    expect($panel->getPlugins())->toHaveCount(1)
        ->and($panel->getPlugin('fixture'))->toBe($second);
});

it('registers a batch of plugins in insertion order', function (): void {
    $panel = new Panel('admin');
    $plugin = FixturePlugin::make();

    $panel->plugins([$plugin]);

    expect($plugin->registered)->toBeTrue()
        ->and($panel->getPlugins())->toHaveKey('fixture');
});

it('returns null for an unknown plugin id', function (): void {
    $panel = new Panel('admin');

    expect($panel->getPlugin('missing'))->toBeNull();
});
```

> **Fato confirmado:** `Panel::__construct(public readonly string $id)` — `new Panel('admin')` é válido. Alternativamente `PanelRegistry::panel('admin')` (create-or-get) retorna a instância, como em `PanelToRegistrySyncTest`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/pest --filter='calls register() eagerly'`
Expected: FAIL — `Call to undefined method Arqel\Core\Panel\Panel::plugin()`.

- [ ] **Step 3: Implementar no Panel**

Em `packages/core/src/Panel/Panel.php`, adicionar o `use` no topo (junto aos outros imports):

```php
use Arqel\Core\Contracts\Plugin;
```

Adicionar o campo junto aos outros `private array` (perto de `$resources`/`$widgets`):

```php
    /** @var array<string, Plugin> keyed por getId() */
    private array $plugins = [];
```

Adicionar os 4 métodos (perto de `resources()`/`widgets()`):

```php
    public function plugin(Plugin $plugin): self
    {
        // Último-vence: registrar o mesmo id 2x substitui (permite um app
        // sobrescrever um plugin de terceiros pelo mesmo id).
        $this->plugins[$plugin->getId()] = $plugin;
        $plugin->register($this);

        return $this;
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    public function plugins(array $plugins): self
    {
        foreach ($plugins as $plugin) {
            $this->plugin($plugin);
        }

        return $this;
    }

    /**
     * @return array<string, Plugin>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getPlugin(string $id): ?Plugin
    {
        return $this->plugins[$id] ?? null;
    }
```

- [ ] **Step 4: Rodar e ver passar**

Run: `vendor/bin/pest --filter=PanelPluginTest`
Expected: PASS (todos os casos unit).

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Panel/Panel.php packages/core/tests/Unit/PanelPluginTest.php
git commit --signoff --no-verify -m "feat(core): register plugins on the Panel builder" -m "Panel::plugin()/plugins()/getPlugins()/getPlugin(); register() runs eagerly, last-wins by id. Milestone 0.19."
```

---

## Task 3: `bootPanelPlugins()` no provider + ordem antes do sync

**Files:**
- Modify: `packages/core/src/ArqelServiceProvider.php` (novo método + chamada no `booted()`)
- Test: `packages/core/tests/Feature/PanelPluginBootTest.php` (novo)
- Test fixture: `packages/core/tests/Fixtures/Plugins/BootRegisteringPlugin.php` (novo)

**Interfaces:**
- Consumes: `Panel::getPlugins()` (Task 2); `PanelRegistry::all()`/`clear()`; `ResourceRegistry::has()`/`clear()`; `Plugin::boot()` (Task 1).
- Produces: `protected function bootPanelPlugins(): void` em `ArqelServiceProvider` — itera `PanelRegistry::all()` e chama `$plugin->boot($panel)` para cada plugin, na ordem. Chamado no `booted()` **imediatamente antes** de `syncPanelResourcesIntoRegistry()`.

- [ ] **Step 1: Escrever o fixture de boot**

Create `packages/core/tests/Fixtures/Plugins/BootRegisteringPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Plugins;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

/**
 * Prova a ordem do lifecycle: registra o resource só em boot().
 * Como bootPanelPlugins() roda ANTES de syncPanelResourcesIntoRegistry(),
 * o UserResource deve acabar no ResourceRegistry.
 */
final class BootRegisteringPlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'boot-registering';
    }

    public function register(Panel $panel): void
    {
        // intencionalmente vazio — o registro acontece em boot()
    }

    public function boot(Panel $panel): void
    {
        $panel->resources([UserResource::class]);
    }
}
```

- [ ] **Step 2: Escrever os testes de feature que falham**

Create `packages/core/tests/Feature/PanelPluginBootTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Plugins\BootRegisteringPlugin;
use Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

beforeEach(function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();
});

/** Helper: invoca um método (protected) do provider por reflexão. */
function invokeProviderMethod(string $method): void
{
    $provider = app()->getProvider(ArqelServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $target = $reflection->getMethod($method);
    $target->setAccessible(true);
    $target->invoke($provider);
}

it('boots each plugin registered on a panel', function (): void {
    $plugin = FixturePlugin::make();

    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin($plugin);

    invokeProviderMethod('bootPanelPlugins');

    expect($plugin->booted)->toBeTrue();
});

it('lets a plugin register a resource in boot() that still becomes a route', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin(BootRegisteringPlugin::make());

    // Ordem real do booted(): bootPanelPlugins ANTES de syncPanelResources.
    invokeProviderMethod('bootPanelPlugins');
    invokeProviderMethod('syncPanelResourcesIntoRegistry');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(UserResource::class))->toBeTrue();
});

it('registers a plugin resource end-to-end from register() into the registry', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin(FixturePlugin::make());

    invokeProviderMethod('bootPanelPlugins');
    invokeProviderMethod('syncPanelResourcesIntoRegistry');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(PostResource::class))->toBeTrue();
});
```

> **Nota para o implementador:** confirme que `PanelRegistry::panel('admin')` retorna a instância de `Panel` (create-or-get) — é o padrão usado em `PanelToRegistrySyncTest`. Se `UserResource`/`PostResource` fixtures não existirem no path esperado, ache-os com `ls packages/core/tests/Fixtures/Resources/` e ajuste os imports.

- [ ] **Step 3: Rodar e ver falhar**

Run: `vendor/bin/pest --filter=PanelPluginBootTest`
Expected: FAIL — `Method bootPanelPlugins does not exist` (ReflectionException).

- [ ] **Step 4: Implementar `bootPanelPlugins()` no provider**

Em `packages/core/src/ArqelServiceProvider.php`, adicionar o método (perto de `syncPanelResourcesIntoRegistry`):

```php
    /**
     * Dispara `boot()` de cada plugin registrado em cada panel.
     *
     * Roda no `$this->app->booted()` ANTES de
     * `syncPanelResourcesIntoRegistry()`, de modo que qualquer
     * `resources([...])` que um plugin adicione em `boot()` ainda seja
     * copiado ao ResourceRegistry e vire rota.
     */
    protected function bootPanelPlugins(): void
    {
        $panelRegistry = $this->app->make(PanelRegistry::class);

        foreach ($panelRegistry->all() as $panel) {
            foreach ($panel->getPlugins() as $plugin) {
                $plugin->boot($panel);
            }
        }
    }
```

- [ ] **Step 5: Encaixar na sequência do `booted()`**

Em `packages/core/src/ArqelServiceProvider.php`, na closure `$this->app->booted(...)` (a que hoje tem `discoverResourcesIfEnabled` → `syncPanelResourcesIntoRegistry` → ...), inserir `bootPanelPlugins()` entre discover e sync:

```php
        $this->app->booted(function (): void {
            $this->discoverResourcesIfEnabled();
            $this->bootPanelPlugins();
            $this->syncPanelResourcesIntoRegistry();
            $this->electDefaultCurrentPanel();
            $this->registerResourceRoutes();
        });
```

- [ ] **Step 6: Rodar e ver passar**

Run: `vendor/bin/pest --filter=PanelPluginBootTest`
Expected: PASS (3 casos).

- [ ] **Step 7: Rodar toda a suíte do core + análise estática**

Run: `vendor/bin/pest --filter=Panel` (sanity de que nada quebrou nos testes de Panel existentes)
Run: `vendor/bin/phpstan analyse packages/core/src --level=max` (se disponível localmente; senão o CI valida)
Expected: PASS / sem erros novos.

- [ ] **Step 8: Commit**

```bash
git add packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/PanelPluginBootTest.php packages/core/tests/Fixtures/Plugins/BootRegisteringPlugin.php
git commit --signoff --no-verify -m "feat(core): boot panel plugins before resource sync" -m "bootPanelPlugins() runs in app->booted() before syncPanelResourcesIntoRegistry so plugins may register resources in boot(). Milestone 0.19."
```

---

## Task 4: Dogfood — `ShowcasePlugin` no app showcase

**Files:**
- Create: `apps/showcase/app/Arqel/Plugins/ShowcasePlugin.php`
- Modify: `apps/showcase/app/Providers/ArqelServiceProvider.php`

**Interfaces:**
- Consumes: `Arqel\Core\Contracts\Plugin` + `CreatesPlugin` (Task 1); um Resource existente do showcase.

- [ ] **Step 1: Identificar um resource do showcase para empacotar**

Run: `ls apps/showcase/app/Arqel/Resources/ 2>/dev/null; grep -n '->resources(\[' -A8 apps/showcase/app/Providers/ArqelServiceProvider.php`
Expected: lista os Resources já registrados. Escolha **um** que já esteja na lista de `->resources([...])` para movê-lo para o plugin (ex.: `AuthorResource`), de modo que a UI permaneça idêntica (o resource continua registrado, só que via plugin). Anote o FQCN.

- [ ] **Step 2: Criar o ShowcasePlugin**

Create `apps/showcase/app/Arqel/Plugins/ShowcasePlugin.php` (ajuste o FQCN do resource ao escolhido no Step 1):

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Plugins;

use App\Arqel\Resources\AuthorResource;
use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;

/**
 * Plugin de dogfood: demonstra o registro in-code de um resource via a
 * Plugin API. Empacota o AuthorResource (antes registrado direto no
 * ServiceProvider) para provar que o registro via plugin é equivalente.
 */
final class ShowcasePlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'showcase';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([AuthorResource::class]);
    }

    public function boot(Panel $panel): void
    {
        // sem efeitos de boot neste exemplo
    }
}
```

> **Cuidado:** `Panel::resources()` faz `array_values($resources)` e **substitui** o array inteiro (não faz merge — confirme lendo o método). Se o showcase chama `->resources([A, B, C])` e depois `->plugin(ShowcasePlugin)` cujo `register()` chama `->resources([Author])`, o segundo `resources()` **sobrescreve** o primeiro. Portanto: no Step 3, **remova** o resource escolhido da lista direta E garanta que o plugin seja aplicado de forma aditiva. Como `resources()` sobrescreve, o plugin deve, em vez de `->resources([Author])`, **acrescentar** ao conjunto existente: use `$panel->resources([...$panel->getResources(), AuthorResource::class])` no `register()` para preservar os já declarados. Ajuste o corpo do `register()` acima para essa forma aditiva.

- [ ] **Step 2b: Corrigir o register() para ser aditivo**

O corpo do `register()` deve preservar os resources já declarados no panel:

```php
    public function register(Panel $panel): void
    {
        $panel->resources([...$panel->getResources(), AuthorResource::class]);
    }
```

- [ ] **Step 3: Ligar o plugin no ServiceProvider do showcase**

Em `apps/showcase/app/Providers/ArqelServiceProvider.php`:
1. Adicionar o import: `use App\Arqel\Plugins\ShowcasePlugin;`
2. **Remover** `AuthorResource::class` da lista direta `->resources([...])`.
3. Encadear `->plugin(ShowcasePlugin::make())` após `->resources([...])` e antes de `setCurrent`:

```php
        $registry
            ->panel('admin')
            ->path('admin')
            // ... demais chamadas fluentes ...
            ->resources([
                PostResource::class,
                // AuthorResource removido daqui — agora vem via ShowcasePlugin
                // ... outros resources ...
            ])
            ->plugin(ShowcasePlugin::make());

        $registry->setCurrent('admin');
```

- [ ] **Step 4: Verificar o dogfood ao vivo (INSUBSTITUÍVEL)**

Subir o stack e validar que o resource ainda aparece na nav (registrado via plugin):

Run (a partir da raiz do monorepo):
```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml up -d --build
# aguardar boot; app na porta 8090
```
Depois: com o Chrome MCP, navegar para `http://localhost:8090/admin`, logar, e confirmar que o item de navegação do resource empacotado (ex.: "Authors") **aparece** e abre a listagem normalmente.
Expected: o resource registrado via `ShowcasePlugin` é indistinguível de um registrado direto — a nav mostra o item e o CRUD funciona.

> Se o item **não** aparecer, é bug real (não flake): provavelmente o `register()` sobrescreveu a lista (ver Step 2b) — revisar a forma aditiva.

- [ ] **Step 5: (Opcional) E2E leve**

Se compensar, adicionar/estender uma spec Playwright que asserta por **conteúdo** que o item de nav do resource empacotado aparece em `/admin`. Rodar localmente com `APP_BASE_URL` na porta 8090 antes de confiar no CI. Se a prova server-side (Task 3) + validação manual (Step 4) já cobrem, pular o E2E e anotar no PR.

- [ ] **Step 6: Commit**

```bash
git add apps/showcase/app/Arqel/Plugins/ShowcasePlugin.php apps/showcase/app/Providers/ArqelServiceProvider.php
git commit --signoff --no-verify -m "feat(showcase): dogfood the Plugin API with ShowcasePlugin" -m "Packages AuthorResource as an in-code plugin to validate Panel::plugin() end-to-end. Milestone 0.19."
```

---

## Task 5: SKILL.md do core + finalização

**Files:**
- Modify: `packages/core/SKILL.md` (documentar a Plugin API)

**Interfaces:** nenhuma nova; documentação.

- [ ] **Step 1: Documentar a Plugin API no SKILL.md do core**

Em `packages/core/SKILL.md`, na seção `## Key Contracts` (ou `## Examples`), adicionar uma entrada em PT-BR descrevendo o contrato `Plugin` e o uso:

```markdown
### Plugin API (in-code)

Um pacote pode injetar conteúdo num Panel implementando `Arqel\Core\Contracts\Plugin`:

​```php
use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;

final class BlogPlugin implements Plugin
{
    use CreatesPlugin; // provê ::make()

    public function getId(): string { return 'blog'; }

    public function register(Panel $panel): void
    {
        $panel->resources([...$panel->getResources(), PostResource::class]);
    }

    public function boot(Panel $panel): void { /* efeitos após todos registrarem */ }
}
​```

Registro na cadeia fluente do Panel:

​```php
Arqel::panel('admin')->plugin(BlogPlugin::make());
​```

- `register()` roda eager (no `->plugin()`); `boot()` roda antes do sync de resources, então plugins podem registrar resources em `boot()` e eles ainda viram rota.
- Plugins são keyed por `getId()` — registrar o mesmo id substitui (permite override).
- `resources()` **substitui** o array; para acrescentar, use o spread `[...$panel->getResources(), X]`.
```

(Remova os `​` zero-width dos code fences — são só para escapar no plano.)

- [ ] **Step 2: Rodar a suíte completa do core uma última vez**

Run: `vendor/bin/pest` (a partir do diretório indicado no Step 0 da Task 1)
Expected: PASS, incluindo `PanelPluginTest` + `PanelPluginBootTest` + os testes de Panel/sync já existentes.

- [ ] **Step 3: Commit**

```bash
git add packages/core/SKILL.md
git commit --signoff --no-verify -m "docs(core): document the in-code Plugin API in SKILL.md" -m "Milestone 0.19."
```

---

## Notas finais de integração (para o orquestrador, não são tasks)

- **NÃO** pushar/abrir PR dentro das tasks — o orquestrador faz após o review de branch inteira (opus).
- Antes do push: `git merge origin/main` (branch pode ficar stale); validar gates CI localmente — commitlint (subjects ≤100, scopes `core`/`showcase` já na allowlist), biome (não aplicável — sem JS aqui), `phpstan`/`pest`.
- O worktree tem `node_modules` root-owned se o Docker rodou (Task 4) → limpeza precisa `sudo`.
- CHANGELOG e bump de versão são passo de release separado (não nas tasks) — a entrega abre PR; a versão 0.19.0 é cortada no fluxo de release já validado.
