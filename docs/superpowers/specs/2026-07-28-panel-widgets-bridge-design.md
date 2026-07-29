# Bridge `Panel::widgets()` → `DashboardRegistry` (0.19b) — Design

**Data:** 2026-07-28
**Milestone:** 0.19b
**Contexto:** escopo diferido do Plugin API (#362), documentado em `docs/superpowers/specs/2026-07-07-plugin-api-design.md` §"Fora de escopo"

## Problema

`Panel::widgets([FooWidget::class])` é aceito pela API fluente do Panel, armazenado em
`Panel::$widgets` e exposto por `Panel::getWidgets()` — e **nunca chega a lugar nenhum**.
Nenhum consumidor lê esse getter: nem código de produção, nem teste (verificado por
varredura no monorepo; os únicos `getWidgets()` encontrados pertencem a `Dashboard`,
método homônimo e não relacionado).

Na prática, hoje um dashboard só existe se a aplicação registrar um `Dashboard` à mão no
seu próprio `ServiceProvider`:

```php
// apps/demo/app/Providers/ArqelServiceProvider.php
$dashboards = $this->app->make(DashboardRegistry::class);
if (! $dashboards->has('main')) {
    $dashboards->register(MainDashboard::make());
}
```

O sintoma para quem usa: declara-se widgets no Panel, nada aparece na tela, e não há
erro nem aviso que explique o porquê. É um campo órfão numa API pública.

### As três restrições que moldam a solução

1. **Direção da dependência.** `arqel-dev/widgets` depende de `arqel-dev/core`; o inverso
   não existe e não deve passar a existir. Portanto `core` não pode referenciar
   `Dashboard`, `DashboardRegistry` ou `Widget`. **O bridge mora em `widgets`.** Isso não é
   uma escolha de estilo — é consequência do grafo de pacotes.

2. **Impedância de forma.** `Panel::$widgets` é uma lista plana de `class-string` **sem
   identidade**. `DashboardRegistry` é um mapa de **containers `Dashboard`**, e
   `Dashboard::make()` exige `id` e `label`. Alguém precisa fornecer essa identidade.

3. **Colisão é fatal.** `DashboardRegistry::register()` lança `InvalidArgumentException`
   em id duplicado. As aplicações atuais (demo, showcase) já registram um dashboard
   `'main'`. Um bridge que registrasse `'main'` cegamente quebraria o boot delas.

## Decisão

O bridge vive em `widgets`, roda em `app->booted()` e tem comportamento **aditivo**:

| Situação | Comportamento |
|---|---|
| Panel sem widgets | No-op. Nada é registrado. |
| Panel com widgets, sem dashboard `main` registrado | Cria `Dashboard::make('main', 'Dashboard')` com os widgets do Panel e registra. |
| Panel com widgets, **com** dashboard `main` já registrado | **Acrescenta** os widgets do Panel ao Dashboard existente via `addWidget()`, preservando os que já estavam. |

O terceiro caso é o que torna o bridge útil para o Plugin API: um plugin chama
`Panel::widgets()` no seu `register()`/`boot()` e seus widgets aparecem no dashboard da
aplicação, sem que o plugin precise conhecer nem substituir o dashboard dela.

### Por que "merge" e não "a aplicação vence"

A alternativa conservadora — registrar só quando não há `main` — deixaria o Panel ser
ignorado em silêncio justamente nas aplicações reais, que já têm um dashboard. Seria
recriar o bug atual com outra roupagem: o dev declara, nada aparece, sem pista do motivo.
O merge faz o que a declaração promete.

### Ordem dos widgets

Os widgets do Panel são acrescentados **depois** dos que a aplicação já registrou. A
ordem final de renderização não depende disso: `Dashboard::resolve()` ordena por
`Widget::getSort()`. A ordem de inserção é apenas o desempate para widgets de mesmo
`sort`, e "aplicação primeiro, plugins depois" é o desempate previsível.

### Identidade do dashboard implícito

`id = 'main'`, `label = 'Dashboard'`. O `id` é `'main'` porque é o que
`DashboardController::show()` já usa como padrão quando a rota não informa um id
(`$registry->get($id ?? 'main')`) — qualquer outro valor produziria um dashboard
inalcançável pela rota `/admin`.

Dashboards adicionais com id próprio continuam sendo registrados diretamente no
`DashboardRegistry` pela aplicação. Dar identidade de dashboard ao Panel
(ex. `Panel::dashboard('vendas', 'Vendas')`) seria ampliar a API pública, o que exige
justificativa própria sob o ADR-019 (API freeze) e está **fora de escopo** aqui.

## Arquitetura

### Onde o código vive

`WidgetsServiceProvider` ganha um hook `booted()` — hoje ele só faz `packageRegistered()`
com os dois singletons. O sync espelha o padrão que `core` já usa para resources em
`ArqelServiceProvider::booted()` (`syncPanelResourcesIntoRegistry()`): defer até `booted`
para enxergar a lista final de panels, independentemente da ordem de registro dos
providers.

```php
// packages/widgets/src/WidgetsServiceProvider.php
public function packageBooted(): void
{
    $this->app->booted(function (): void {
        $this->syncPanelWidgetsIntoDashboardRegistry();
    });
}
```

### Ordem de boot (crítica)

O `core` já roda, dentro do seu próprio `booted()`, a sequência:

```
discoverResourcesIfEnabled()
bootPanelPlugins()          ← plugins mutam o Panel aqui
syncPanelResourcesIntoRegistry()
electDefaultCurrentPanel()
registerResourceRoutes()
```

O sync de widgets precisa rodar **depois** de `bootPanelPlugins()`, senão os widgets que
um plugin adiciona no `boot()` não seriam vistos. Como `widgets` depende de `core`, seu
provider é registrado depois, e callbacks de `app->booted()` executam em ordem de
registro — a precedência é satisfeita naturalmente. **Isto precisa de um teste explícito**,
porque é uma garantia de ordem entre pacotes e não uma propriedade local.

### Quais panels são lidos

O sync itera **todos os panels registrados** (`PanelRegistry::all()`), espelhando
`syncPanelResourcesIntoRegistry()`, que faz exatamente isso para resources. Não se
restringe ao panel corrente: em boot o panel corrente ainda pode não estar eleito
(`electDefaultCurrentPanel()` roda na mesma sequência), e o middleware pode trocá-lo por
request — ler "o corrente" no boot seria arbitrário.

**Consequência em app multi-panel:** se dois panels declararem widgets, todos convergem
para o mesmo dashboard `main`. Isso é aceito conscientemente nesta iteração, pela mesma
razão que o dashboard implícito se chama `main`: a rota `/admin` resolve um único
dashboard padrão, e não existe hoje um vínculo panel↔dashboard que permita separá-los.
Segregar widgets por panel exigiria dar identidade de dashboard ao Panel — a ampliação de
API que este spec deixa explicitamente fora de escopo.

Na prática o caso é raro: apps multi-panel normalmente registram seus dashboards
nomeados à mão. Um teste cobre o comportamento para que ele seja documentado e
intencional, não acidental.

Se não houver panel algum, ou nenhum panel declarar widgets, o sync é no-op.

### Tipagem na fronteira

`Panel::getWidgets()` devolve `array<int, class-string>` genérico — o `core` não conhece
`Widget`, então não pode restringir. Do lado do bridge, `Dashboard::addWidget()` já valida
(`is_subclass_of($widget, Widget::class)`) e **descarta silenciosamente** o que não for
Widget. O bridge não duplica essa validação: uma class-string inválida no Panel é
descartada na fronteira, sem derrubar o boot — coerente com o resto do pacote widgets,
que trata má configuração como algo a ignorar, não a explodir.

### Mutação pós-registro é segura

`DashboardRegistry::get()` devolve o próprio objeto `Dashboard` (handle, não cópia), e
apenas `id`/`label`/`path` são `readonly` — a lista de widgets não é. Mutar o Dashboard
recuperado do registry já reflete no registry; não é preciso remover e re-registrar (o que
aliás seria impossível sem um `unregister()`, que não existe).

## Testes

Feature, em `packages/widgets/tests/`, invocando o sync pelo provider (padrão de
`PanelToRegistrySyncTest` no core):

1. **Panel sem widgets** → registry permanece vazio (no-op, sem dashboard fantasma).
2. **Panel com widgets, sem `main`** → dashboard `main` criado, contendo exatamente os widgets do Panel.
3. **Panel com widgets, `main` já registrado** → widgets do Panel acrescentados; **os widgets originais da aplicação continuam presentes** (a asserção que prova ausência de clobbering).
4. **Ordem de boot** → um widget adicionado no `boot()` de um Plugin chega ao dashboard (prova que o sync roda depois de `bootPanelPlugins()`).
5. **Composição de dois plugins** → ambos os conjuntos de widgets coexistem, nenhum sobrescreve o outro. Espelha o teste de composição que o #362 fez para resources.
6. **Class-string inválida no Panel** → descartada em silêncio, boot não quebra, demais widgets intactos.
7. **Dois panels com widgets** → ambos os conjuntos convergem para `main` (documenta a
   consequência do multi-panel como comportamento intencional).

## Fora de escopo

- **Múltiplos dashboards a partir do Panel** (`Panel::dashboard(id, label)`) — ampliação de API pública sob ADR-019.
- **`unregister()`/substituição no `DashboardRegistry`** — não é necessário para o merge.
- **Bridge de widgets no lado React** — o `DashboardController` já renderiza o que está no registry; nada muda no frontend.
- **Deprecar `Panel::widgets()`** — o oposto: este trabalho o torna funcional.

## Impacto

Sem breaking change. Sem dependência nova (`widgets` já requer `core`). Aplicações que
não usam `Panel::widgets()` não percebem diferença: o sync é no-op quando a lista está
vazia. Aplicações que já registram `main` à mão continuam funcionando, e passam a poder
receber widgets de plugins.
