# Bridge `Panel::widgets()` → `DashboardRegistry` — Plano de Implementação

> **Para quem executa:** use `superpowers:subagent-driven-development` (recomendado) ou
> `superpowers:executing-plans` para executar tarefa a tarefa. Os passos usam checkbox
> (`- [ ]`) para acompanhamento.

**Objetivo:** fazer `Panel::widgets([FooWidget::class])` chegar ao dashboard renderizado,
fechando o campo órfão `Panel::getWidgets()`.

**Arquitetura:** um sync deferido em `WidgetsServiceProvider`, rodando em `app->booted()`,
que copia os widgets de todos os panels para o `Dashboard` de id `main` — criando-o se não
existir, ou acrescentando aos widgets já registrados pela aplicação se existir.

**Spec:** `docs/superpowers/specs/2026-07-28-panel-widgets-bridge-design.md`

## Restrições globais

Valores exatos, válidos para todas as tarefas:

- **PHP 8.3+**, `declare(strict_types=1)` em todo arquivo novo.
- **`core` NÃO pode depender de `widgets`.** Sentido da dependência: `widgets` → `core`.
  Nenhum arquivo em `packages/core/` pode referenciar `Dashboard`, `DashboardRegistry` ou
  `Widget`. **Nenhuma tarefa deste plano altera `packages/core/`.**
- **Nenhuma dependência nova** em `composer.json`.
- Classes `final` por padrão.
- Testes em **Pest 3** com sintaxe funcional (`it('...', function (): void {})`).
- Commits: Conventional Commits + DCO (`--signoff`). Scope **`widgets`**.
  Use `--no-verify` (o husky/lint-staged quebra neste host).
- Antes de push: `composer run lint` **a partir da raiz do repositório** (não `vendor/bin/pint`
  por arquivo — o ruleset da raiz difere).
- PHPStan **não roda localmente** neste ambiente (symlink loop do testbench-core). Confie no CI.

## Estrutura de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `packages/widgets/src/WidgetsServiceProvider.php` | **Modificar.** Ganha `packageBooted()` + o método de sync. |
| `packages/widgets/tests/Fixtures/SecondaryWidget.php` | **Criar.** Segundo widget de teste, para provar ausência de clobbering. |
| `packages/widgets/tests/Fixtures/WidgetPlugin.php` | **Criar.** Plugin que declara widgets, para o teste de ordem de boot. |
| `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php` | **Criar.** Os 7 cenários do spec. |
| `packages/widgets/SKILL.md` | **Modificar.** Documentar a API pública nova. |
| `CHANGELOG.md` | **Modificar.** Entrada em `[Unreleased]`. |

O sync fica no próprio provider (não numa classe separada) para espelhar
`ArqelServiceProvider::syncPanelResourcesIntoRegistry()`, que é um `protected` método do
provider. Consistência com o padrão existente vale mais que extração prematura.

---

## Tarefa 1: Sync básico — cria o dashboard quando não existe

**Arquivos:**
- Modificar: `packages/widgets/src/WidgetsServiceProvider.php`
- Criar: `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`

**Interfaces:**
- Consome: `PanelRegistry::all(): array<string, Panel>` e `Panel::getWidgets(): array<int, class-string>` (de `core`); `DashboardRegistry::{has,get,register}` e `Dashboard::{make,widgets,getWidgets}` (de `widgets`).
- Produz: `WidgetsServiceProvider::syncPanelWidgetsIntoDashboardRegistry(): void` (protected) — as tarefas 2-4 estendem este mesmo método.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Widgets\DashboardRegistry;
use Arqel\Widgets\Tests\Fixtures\CounterWidget;
use Arqel\Widgets\WidgetsServiceProvider;

/**
 * O bridge 0.19b: widgets declarados num Panel precisam chegar ao
 * DashboardRegistry, senão `Panel::widgets()` é um campo órfão.
 *
 * O provider já bootou quando o teste roda, então cada caso reinvoca
 * o hook por reflection — mesmo padrão de PanelToRegistrySyncTest no core.
 */
function invokeWidgetSync(): void
{
    $provider = app()->getProvider(WidgetsServiceProvider::class);
    $method = new ReflectionMethod($provider, 'syncPanelWidgetsIntoDashboardRegistry');
    $method->setAccessible(true);
    $method->invoke($provider);
}

beforeEach(function (): void {
    app(PanelRegistry::class)->clear();
    app(DashboardRegistry::class)->clear();
});

it('creates the main dashboard from panel widgets when none is registered', function (): void {
    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    $dashboard = app(DashboardRegistry::class)->get('main');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard->getWidgets())->toBe([CounterWidget::class]);
});
```

- [ ] **Passo 2: Rodar e confirmar a falha**

```bash
packages/widgets/vendor/bin/pest --filter="creates the main dashboard" --configuration=packages/widgets/phpunit.xml
```

Esperado: **FAIL** com `ReflectionException: Method ... does not exist`.

Se `vendor/bin/pest` não existir em `packages/widgets/`, rode antes:
`composer --working-dir=packages/widgets install --ignore-platform-req=ext-zip --ignore-platform-req=ext-curl`

- [ ] **Passo 3: Implementar o mínimo**

Em `packages/widgets/src/WidgetsServiceProvider.php`, adicionar os imports e dois métodos:

```php
use Arqel\Core\Panel\PanelRegistry;

    /**
     * Copy widgets declared on any Panel into the dashboard registry.
     *
     * Deferred to `booted` so every panel — including those a plugin
     * mutates in `Plugin::boot()` — is visible. `widgets` is registered
     * after `core`, so this callback runs after core's own `booted`
     * hook, which is where `bootPanelPlugins()` lives.
     */
    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            $this->syncPanelWidgetsIntoDashboardRegistry();
        });
    }

    /**
     * Panels hold a flat list of `class-string` without dashboard
     * identity; the registry holds `Dashboard` containers keyed by id.
     * The bridge wraps the former into the latter under the id `main`,
     * which is what `DashboardController` falls back to for `/admin`.
     */
    protected function syncPanelWidgetsIntoDashboardRegistry(): void
    {
        $panels = $this->app->make(PanelRegistry::class);
        $dashboards = $this->app->make(DashboardRegistry::class);

        $declared = [];
        foreach ($panels->all() as $panel) {
            foreach ($panel->getWidgets() as $widgetClass) {
                $declared[] = $widgetClass;
            }
        }

        if ($declared === []) {
            return;
        }

        $dashboards->register(
            Dashboard::make('main', 'Dashboard')->widgets($declared)
        );
    }
```

Adicionar `use Arqel\Widgets\Dashboard;`? **Não** — `Dashboard` está no mesmo namespace
`Arqel\Widgets` do provider, então não precisa de import. `DashboardRegistry` idem.
Apenas `PanelRegistry` (de `Arqel\Core\Panel`) precisa ser importado.

- [ ] **Passo 4: Rodar e confirmar que passa**

```bash
packages/widgets/vendor/bin/pest --filter="creates the main dashboard" --configuration=packages/widgets/phpunit.xml
```

Esperado: **PASS**.

- [ ] **Passo 5: Commit**

```bash
git add packages/widgets/src/WidgetsServiceProvider.php packages/widgets/tests/Feature/PanelWidgetsSyncTest.php
git commit --no-verify --signoff -m "feat(widgets): sync panel widgets into the dashboard registry

Panel::getWidgets() era um campo órfão — nenhum consumidor em produção
ou teste. O sync roda em app->booted() no provider de widgets, que é
registrado depois do core, garantindo que plugins já mutaram o Panel.

Ref milestone 0.19b, spec docs/superpowers/specs/2026-07-28-panel-widgets-bridge-design.md"
```

---

## Tarefa 2: No-op quando não há widgets

**Arquivos:**
- Modificar: `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`

**Interfaces:**
- Consome: `syncPanelWidgetsIntoDashboardRegistry()` da Tarefa 1.
- Produz: nada novo — valida o early-return já escrito.

- [ ] **Passo 1: Escrever os testes**

Acrescentar ao final de `PanelWidgetsSyncTest.php`:

```php
it('registers no dashboard when no panel declares widgets', function (): void {
    app(PanelRegistry::class)->panel('admin');

    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->has('main'))->toBeFalse();
});

it('registers no dashboard when there are no panels at all', function (): void {
    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->all())->toBe([]);
});
```

- [ ] **Passo 2: Rodar**

```bash
packages/widgets/vendor/bin/pest --configuration=packages/widgets/phpunit.xml
```

Esperado: **PASS** — o `if ($declared === []) return;` da Tarefa 1 já cobre ambos.
Se algum falhar, o early-return está no lugar errado; corrija antes de seguir.

- [ ] **Passo 3: Commit**

```bash
git add packages/widgets/tests/Feature/PanelWidgetsSyncTest.php
git commit --no-verify --signoff -m "test(widgets): cover the no-op paths of the panel widgets sync

Ref milestone 0.19b"
```

---

## Tarefa 3: Merge no dashboard existente (o caso central)

Este é o comportamento que distingue o bridge de uma solução ingênua: sem ele,
`DashboardRegistry::register()` lançaria `InvalidArgumentException` em apps que já
registram `main` — demo e showcase, hoje.

**Arquivos:**
- Modificar: `packages/widgets/src/WidgetsServiceProvider.php`
- Modificar: `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`
- Criar: `packages/widgets/tests/Fixtures/SecondaryWidget.php`

**Interfaces:**
- Consome: `Dashboard::addWidget(Widget|class-string<Widget>): self` — acrescenta (vs. `widgets()` que substitui) e já valida `is_subclass_of(Widget::class)`.
- Produz: `SecondaryWidget` (fixture reutilizada pelas Tarefas 4 e 5).

- [ ] **Passo 1: Criar a segunda fixture**

`packages/widgets/tests/Fixtures/SecondaryWidget.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Segundo widget de teste — existe para provar que o merge do bridge
 * acrescenta sem sobrescrever o que a aplicação já registrou.
 */
final class SecondaryWidget extends Widget
{
    protected string $type = 'secondary';

    protected string $component = 'SecondaryWidget';

    public function data(): array
    {
        return ['ok' => true];
    }
}
```

- [ ] **Passo 2: Escrever o teste que falha**

Acrescentar a `PanelWidgetsSyncTest.php` (e `use Arqel\Widgets\Tests\Fixtures\SecondaryWidget;` no topo, junto dos demais imports):

```php
it('appends panel widgets to a dashboard the app already registered', function (): void {
    // A aplicação registra o seu dashboard primeiro — o caso de demo/showcase.
    app(DashboardRegistry::class)->register(
        Arqel\Widgets\Dashboard::make('main', 'App Dashboard')->widgets([SecondaryWidget::class])
    );

    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    $widgets = app(DashboardRegistry::class)->get('main')->getWidgets();

    // O widget da app continua presente — nada de clobbering.
    expect($widgets)->toContain(SecondaryWidget::class)
        ->and($widgets)->toContain(CounterWidget::class)
        ->and($widgets)->toHaveCount(2);
});

it('keeps the label the app chose when merging', function (): void {
    app(DashboardRegistry::class)->register(
        Arqel\Widgets\Dashboard::make('main', 'App Dashboard')
    );
    app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    invokeWidgetSync();

    expect(app(DashboardRegistry::class)->get('main')->label)->toBe('App Dashboard');
});
```

- [ ] **Passo 3: Rodar e confirmar a falha**

```bash
packages/widgets/vendor/bin/pest --filter="appends panel widgets" --configuration=packages/widgets/phpunit.xml
```

Esperado: **FAIL** com `InvalidArgumentException: DashboardRegistry already has a dashboard registered with id [main].`

Essa exceção é a prova de que o merge é necessário — sem ele o bridge quebra o boot de
qualquer app que já tenha um dashboard.

- [ ] **Passo 4: Implementar o merge**

Substituir o bloco final de `syncPanelWidgetsIntoDashboardRegistry()`:

```php
        if ($declared === []) {
            return;
        }

        // `get()` devolve o próprio objeto (handle, não cópia) e a lista de
        // widgets não é readonly — mutar aqui já reflete no registry, sem
        // precisar de um `unregister()` que não existe.
        $existing = $dashboards->get(self::PANEL_DASHBOARD_ID);

        if ($existing !== null) {
            foreach ($declared as $widgetClass) {
                $existing->addWidget($widgetClass);
            }

            return;
        }

        $dashboards->register(
            Dashboard::make(self::PANEL_DASHBOARD_ID, 'Dashboard')->widgets($declared)
        );
```

E adicionar a constante no topo da classe, junto aos demais membros:

```php
    /**
     * Id do dashboard implícito. É `main` porque é o fallback que
     * `DashboardController::show()` usa para a rota `/admin` — qualquer
     * outro valor produziria um dashboard inalcançável.
     */
    private const PANEL_DASHBOARD_ID = 'main';
```

- [ ] **Passo 5: Rodar a suíte inteira**

```bash
packages/widgets/vendor/bin/pest --configuration=packages/widgets/phpunit.xml
```

Esperado: **PASS** em tudo, inclusive nos testes das Tarefas 1-2.

- [ ] **Passo 6: Commit**

```bash
git add packages/widgets/src/WidgetsServiceProvider.php packages/widgets/tests/
git commit --no-verify --signoff -m "feat(widgets): merge panel widgets into an existing main dashboard

DashboardRegistry::register() lanca em id duplicado, e demo/showcase ja
registram 'main' — registrar cegamente quebraria o boot delas. O sync
agora acrescenta via addWidget() quando o dashboard existe, preservando
os widgets e o label que a aplicacao escolheu.

Ref milestone 0.19b"
```

---

## Tarefa 4: Ordem de boot — widgets de um plugin chegam ao dashboard

Esta é a razão de ser do 0.19b. É também a única garantia **entre pacotes** do plano
(`widgets` boota depois de `core`), por isso merece teste explícito em vez de confiança.

**Arquivos:**
- Criar: `packages/widgets/tests/Fixtures/WidgetPlugin.php`
- Modificar: `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`

**Interfaces:**
- Consome: `Arqel\Core\Contracts\Plugin` (`getId`/`register`/`boot`) e o trait `Arqel\Core\Panel\Concerns\CreatesPlugin` (`::make()`), ambos de `core`, entregues pelo #362.

- [ ] **Passo 1: Confirmar a assinatura real do contrato**

Antes de escrever a fixture, leia `packages/core/src/Contracts/Plugin.php` e confirme os
tipos exatos de `register()` e `boot()`. O plano assume `register(Panel $panel): void` e
`boot(Panel $panel): void`, mas **use o que estiver no arquivo** — o contrato é a verdade.

- [ ] **Passo 2: Criar a fixture do plugin**

`packages/widgets/tests/Fixtures/WidgetPlugin.php` (ajuste as assinaturas ao Passo 1):

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Core\Contracts\Plugin;
use Arqel\Core\Panel\Concerns\CreatesPlugin;
use Arqel\Core\Panel\Panel;

/**
 * Plugin de teste que injeta um widget no Panel durante `boot()`.
 *
 * Prova a garantia de ordem que o bridge depende: o sync de widgets
 * roda depois de `bootPanelPlugins()` do core, então widgets que só
 * existem a partir do boot de um plugin ainda alcançam o dashboard.
 */
final class WidgetPlugin implements Plugin
{
    use CreatesPlugin;

    public function getId(): string
    {
        return 'test-widget-plugin';
    }

    public function register(Panel $panel): void
    {
        // Nada aqui de propósito: o widget entra em boot(), que é o
        // caso difícil.
    }

    public function boot(Panel $panel): void
    {
        $panel->widgets([...$panel->getWidgets(), SecondaryWidget::class]);
    }
}
```

Nota: `Panel::widgets()` **substitui** a lista, então o spread preserva o que já existia.

- [ ] **Passo 3: Escrever o teste**

```php
it('picks up widgets a plugin adds during boot', function (): void {
    $panel = app(PanelRegistry::class)->panel('admin')->widgets([CounterWidget::class]);

    // Simula o que `bootPanelPlugins()` faz no core: o plugin muta o
    // Panel antes de o sync de widgets rodar.
    WidgetPlugin::make()->boot($panel);

    invokeWidgetSync();

    $widgets = app(DashboardRegistry::class)->get('main')->getWidgets();

    expect($widgets)->toContain(CounterWidget::class)
        ->and($widgets)->toContain(SecondaryWidget::class);
});
```

- [ ] **Passo 4: Rodar**

```bash
packages/widgets/vendor/bin/pest --filter="picks up widgets a plugin" --configuration=packages/widgets/phpunit.xml
```

Esperado: **PASS** sem mudar o código de produção — o sync já lê o estado final do Panel.
Se falhar, o sync está lendo os panels cedo demais; revise a Tarefa 1.

- [ ] **Passo 5: Commit**

```bash
git add packages/widgets/tests/
git commit --no-verify --signoff -m "test(widgets): prove plugin-added widgets reach the dashboard

Ref milestone 0.19b"
```

---

## Tarefa 5: Casos de borda — entrada inválida e multi-panel

**Arquivos:**
- Modificar: `packages/widgets/tests/Feature/PanelWidgetsSyncTest.php`

- [ ] **Passo 1: Escrever os testes**

```php
it('silently drops entries that are not widgets', function (): void {
    app(PanelRegistry::class)->panel('admin')->widgets([
        CounterWidget::class,
        'App\\Does\\Not\\Exist',
        stdClass::class,
    ]);

    invokeWidgetSync();

    // `Dashboard::widgets()`/`addWidget()` filtram non-Widget: má
    // configuração não derruba o boot do painel.
    expect(app(DashboardRegistry::class)->get('main')->getWidgets())
        ->toBe([CounterWidget::class]);
});

it('collects widgets from every registered panel', function (): void {
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->widgets([CounterWidget::class]);
    $panels->panel('reports')->widgets([SecondaryWidget::class]);

    invokeWidgetSync();

    // Documenta a consequência do multi-panel: tudo converge para `main`,
    // porque não existe vínculo panel↔dashboard (spec, "Quais panels são lidos").
    expect(app(DashboardRegistry::class)->get('main')->getWidgets())
        ->toHaveCount(2);
});
```

- [ ] **Passo 2: Rodar a suíte inteira**

```bash
packages/widgets/vendor/bin/pest --configuration=packages/widgets/phpunit.xml
```

Esperado: **PASS**. Nenhuma mudança de produção deve ser necessária — a validação já vive
em `Dashboard`. Se o teste de entrada inválida falhar, **não** adicione validação no
provider: investigue por que `Dashboard` deixou passar.

- [ ] **Passo 3: Commit**

```bash
git add packages/widgets/tests/
git commit --no-verify --signoff -m "test(widgets): cover invalid entries and multi-panel collection

Ref milestone 0.19b"
```

---

## Tarefa 6: Documentação

**Arquivos:**
- Modificar: `packages/widgets/SKILL.md`
- Modificar: `CHANGELOG.md`
- Modificar: `apps/docs/reference/php/widgets.md` (+ `pt-BR`/`es`)

- [ ] **Passo 1: SKILL.md**

Em `packages/widgets/SKILL.md`, documentar sob *Key Contracts* ou *Conventions*: que
`Panel::widgets([...])` alimenta o dashboard `main`; que o sync é aditivo (não substitui um
dashboard da aplicação); e que dashboards com id próprio continuam sendo registrados
direto no `DashboardRegistry`. Siga o tom e a estrutura do arquivo existente.

- [ ] **Passo 2: CHANGELOG**

Em `[Unreleased]`, sob `### Added`:

```markdown
- **widgets (bridge Panel→Dashboard):** widgets declarados via `Panel::widgets([...])`
  agora chegam ao dashboard renderizado. Antes `Panel::getWidgets()` era um campo órfão —
  a declaração era aceita e silenciosamente descartada. O sync roda no boot e é **aditivo**:
  cria o dashboard `main` se não houver, ou acrescenta aos widgets que a aplicação já
  registrou, preservando-os. Habilita plugins a injetarem widgets no dashboard da app
  (milestone 0.19b).
```

- [ ] **Passo 3: Docs de referência**

Acrescentar a `apps/docs/reference/php/widgets.md` uma subseção sobre o bridge. **Traduzir
para `pt-BR` e `es`** — a sidebar é uma lista única para os 3 locales, então divergência
vira link ou conteúdo faltante. Siga a terminologia das páginas já traduzidas
(substantivos de domínio em inglês, prosa traduzida).

- [ ] **Passo 4: Commit**

```bash
git add packages/widgets/SKILL.md CHANGELOG.md apps/docs/
git commit --no-verify --signoff -m "docs(widgets): document the Panel->Dashboard widget bridge

Ref milestone 0.19b"
```

---

## Fechamento

- [ ] **Lint a partir da raiz** (obrigatório antes do push — o ruleset da raiz difere do pint por arquivo):

```bash
composer run lint
```

Se acusar, rode `composer run format` e recommite.

- [ ] **Suíte completa do pacote:**

```bash
packages/widgets/vendor/bin/pest --configuration=packages/widgets/phpunit.xml
```

- [ ] **Suíte do core** — o sync não altera `core`, mas confirma que nada regrediu na
  fronteira entre pacotes:

```bash
packages/core/vendor/bin/pest --configuration=packages/core/phpunit.xml
```

- [ ] **Abrir PR** com base em `main`. No corpo: o problema (campo órfão), a restrição de
  dependência que fixou a arquitetura, a decisão de merge-vs-ceder e seu porquê, e a
  consequência conhecida do multi-panel. Referenciar o spec.

## Fora de escopo (não implemente)

- `Panel::dashboard(id, label)` ou qualquer ampliação da API pública do `Panel` — está sob ADR-019 (API freeze) e exige justificativa própria.
- `DashboardRegistry::unregister()` — desnecessário: o merge muta o objeto por handle.
- Qualquer alteração em `packages/core/`.
- Mudanças no frontend React — `DashboardController` já renderiza o que estiver no registry.
