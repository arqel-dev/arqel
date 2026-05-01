# Encontrando plugins

> Como descobrir, avaliar e instalar plugins do Arqel Marketplace.

Esta página cobre o lado do **consumidor** do marketplace — desenvolvedores que querem estender seu admin Arqel com fields, widgets, integrações ou temas mantidos pela comunidade.

## Caminhos de descoberta

O marketplace expõe seis caminhos públicos via REST (e a UI em `arqel.dev/marketplace` consome todos eles):

### 1. Busca por categoria

Categorias raiz default: `fields`, `widgets`, `themes`, `integrations`, `utilities`. Cada uma pode ter sub-categorias (relação `parent_id` self-referencing em `arqel_plugin_categories`).

```http
GET /api/marketplace/categories?root=1
GET /api/marketplace/categories/widgets/plugins
```

A primeira retorna todas as categorias raiz com `children` eager-loaded. A segunda retorna plugins published de uma categoria específica, paginados.

### 2. Busca textual

Search libre cobre `name` + `description`:

```http
GET /api/marketplace/plugins?search=calendar&type=widget&per_page=30
```

Parâmetros suportados: `type` (enum), `search` (string), `per_page` (clamp 1-100), `page`. Apenas plugins com `status=published` aparecem — drafts, pending e archived são opacos (404 explícito).

### 3. Trending

Plugins em alta nos últimos 7 dias. O score é calculado pelo `TrendingScoreCalculator`:

```
score = installations_last_7d * 1.0 + recent_positive_reviews_30d * 5.0
```

Reviews positivas (≥4 estrelas) pesam 5x mais que installations cruas — sinal social vale mais que pico de download anônimo.

```http
GET /api/marketplace/trending
```

Retorna top 20 plugins published ordenados por `trending_score` desc. Recálculo é diário (host app deve agendar `Schedule::command('arqel:marketplace:trending')->daily()`).

### 4. Featured (editor's picks)

Curadoria manual feita pela equipe Arqel. Ativada via `POST /admin/plugins/{slug}/feature` (Gate `marketplace.feature`).

```http
GET /api/marketplace/featured
```

Ordenado por `featured_at` desc — destaques mais recentes primeiro.

### 5. New this week

Plugins published nos últimos N dias (default 7, clamp 1-90):

```http
GET /api/marketplace/new?days=14
```

Útil para watchlists e newsletters semanais.

### 6. Most popular (all-time)

Ranking absoluto por contagem de instalações:

```http
GET /api/marketplace/popular
```

Limite 20. Útil quando trending está volátil mas você quer "o que comunidade adotou de fato".

## Como avaliar um plugin

Antes de instalar, abra a página de detalhe do plugin (`/marketplace/{slug}`) e verifique cinco sinais:

### Downloads

`installations.count()` é o medidor mais cru. Plugins com >1k instalações são considerados estáveis pela maioria dos times. Plugins novos podem ser excelentes mas exigem que você leia o código antes de adotar em produção.

### Reviews + ratings

A relação `arqel_plugin_reviews` armazena estrelas (1-5), comentário e dois contadores: `helpful_count` e `unhelpful_count`. Sort options:

```http
GET /api/marketplace/plugins/{slug}/reviews?sort=helpful
GET /api/marketplace/plugins/{slug}/reviews?sort=recent
GET /api/marketplace/plugins/{slug}/reviews?sort=rating
```

`helpful` (default) ordena por `helpful_count` desc — reviews que outros usuários acharam úteis flutuam para o topo. Reviews `pending` (recém-criadas) não aparecem na listagem pública até passarem por moderation queue.

A flag `verified_purchaser` indica se o reviewer comprou o plugin (somente para premium plugins). Em plugins free essa coluna fica sempre `false`.

### Security badges

Cada plugin tem um histórico de scans em `arqel_plugin_security_scans` rodado pelo `SecurityScanner`. O selo exibido na página é o resultado do último scan:

| Badge | Significado |
|---|---|
| 🟢 **Passed** | Sem findings ou apenas warnings `low` |
| 🟡 **Flagged** | Findings `high` ou `medium` — leia detalhes antes de instalar |
| 🔴 **Failed** | Finding `critical` — plugin foi auto-delistado (`status=archived`) |
| ⏳ **Pending/Running** | Scan em curso, volte mais tarde |

Cobre vulnerability lookup, license check (allow-list `MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) e (futuramente) static analysis para suspicious patterns. Detalhes em [Boas práticas de segurança](./security-best-practices.md).

### Compatibility constraint

Todo plugin declara em `composer.json`:

```json
{
  "extra": {
    "arqel": {
      "plugin-type": "field-pack",
      "compat": {
        "arqel": "^1.0"
      }
    }
  }
}
```

O constraint segue semver. Antes de instalar, confira se `compat.arqel` cobre a versão que você roda em produção. O `PluginConventionValidator` (executado no momento da submissão) já garante que o constraint é semver válido — mas a casamento com a sua versão é responsabilidade sua.

### Maintainer activity

A página de detalhe lista todas as releases (relação `arqel_plugin_versions`). Plugins sem release nos últimos 12 meses devem ser tratados como _at-risk_, especialmente se o framework Arqel teve major bumps no período.

## Instalação

A instalação é um wrapper sobre Composer + npm que respeita as duas linguagens do framework.

### CLI: `arqel install`

(Comando entregue em MKTPLC-005, previsto na `Console` do pacote `arqel/marketplace`.)

```bash
php artisan arqel:install acme/stripe-card
```

Por trás dos panos:

1. Resolve o slug `acme/stripe-card` no marketplace via `GET /plugins/{slug}` para obter `composer_package` e `npm_package`.
2. Roda `composer require <composer_package>:<latest_version>`.
3. Se houver `npm_package`, roda `pnpm add -D <npm_package>` no workspace `apps/admin` (ou no path configurado).
4. Registra o service provider via `php artisan vendor:publish --tag=plugin-providers` se necessário.
5. Persiste a instalação em `arqel_plugin_installations` com `anonymized_user_hash` (não envia user data raw).

### Composer direto

Para plugins free você sempre pode bypassar o CLI:

```bash
composer require acme/stripe-card
pnpm add -D @acme/arqel-stripe-fields
```

Lembre-se que neste caminho você **não** é contado nas estatísticas de installations e portanto não influencia o trending score do plugin. Se você quer apoiar autores que você gosta, prefira `arqel:install`.

### Plugins premium

Premium plugins exigem purchase + license key:

```http
POST /api/marketplace/plugins/{slug}/purchase           # initiate
POST /api/marketplace/plugins/{slug}/purchase/confirm   # após pagamento
GET  /api/marketplace/plugins/{slug}/download           # com license válida
```

Detalhes em [Pagamentos & licenças](./payments-and-licensing.md). O `arqel:install` faz esse fluxo automaticamente quando detecta `price_cents > 0`, redirecionando o usuário para checkout via `MockPaymentGateway` (default) ou `StripeConnectGateway` (futuro).

## Verificando o que está instalado

O comando `arqel:plugin:list` (entregue em MKTPLC-003) lê metadados via `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')` e imprime tabela:

```bash
php artisan arqel:plugin:list

+-------------------------+---------+-------------+--------------+-----------+
| Name                    | Version | Plugin Type | Category     | Status    |
+-------------------------+---------+-------------+--------------+-----------+
| acme/stripe-card        | 1.2.0   | field-pack  | integrations | installed |
| beta/markdown-editor    | 0.4.1   | field-pack  | fields       | installed |
| gamma/slack-notify      | 2.0.3   | integration | integrations | installed |
+-------------------------+---------+-------------+--------------+-----------+
```

Adicione `--validate` para rodar o `PluginConventionValidator` em cada install:

```bash
php artisan arqel:plugin:list --validate
```

Saída detalhada por plugin com resultado dos checks (`composer_type`, `plugin_type_enum`, `compat_semver`, `category_present`, `installation_instructions`, `keywords_present`). Útil quando você suspeita de plugin com convention divergente após upgrade.

## Próximos passos

- Plugin parece interessante mas você quer ler o código primeiro? Cada plugin tem `github_url` na página de detalhe.
- Encontrou bug? Use o link "Report issue" que aponta direto para issues do repositório do plugin.
- Quer reviews seu próprio plugin? Veja [Publicando plugins](./publishing.md).
