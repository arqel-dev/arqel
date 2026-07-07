# Global Record Search — Design

**Data:** 2026-07-07
**Milestone:** 0.19 (Extensibilidade + Notifications) — lacuna competitiva #6 (Global Search de *registros*)
**Escopo:** buscar registros cross-resource na command palette (Cmd+K), 100% aditivo no PHP.

## Contexto

A command palette (Cmd+K) já existe e navega para *resources* (`NavigationCommandProvider`
emite "Go to {plural}"). O que falta é buscar *registros*: digitar "ana" e ver
"Ana Lima" (User #42), "Ana's Corp" (Company #9), cada resultado levando ao edit do
registro. É a última lacuna competitiva de busca antes do 1.0 (o Filament oferece via
`getGloballySearchableAttributes()`).

A arquitetura existente é favorável:

- `CommandRegistry` (singleton) mescla comandos estáticos + **providers lazy** consultados
  por request com `(?Authenticatable $user, string $query)`.
- Endpoint `GET /admin/commands?q=...` (`CommandPaletteController`) já delega a
  `CommandRegistry::resolveFor($user, $query)` e serializa via `Command::toArray()`.
- `CommandPalette.tsx` já faz debounced fetch da query digitada e renderiza os `Command`s
  por `category`.

**Consequência:** Global Search de registros é um novo `CommandProvider` no lado PHP.
O endpoint, o controller e o React **não mudam**. A feature inteira é testável em Pest
(rodável localmente), sem depender do toolchain JS.

## Princípio central

100% aditivo no PHP. Nenhuma mudança em `CommandPaletteController` nem em
`CommandPalette.tsx`. A superfície nova é:

1. `RecordSearchCommandProvider` — consulta o banco e emite um `Command` por registro.
2. Extensão retrocompatível de `Command` — campo interno `?int $rankScore` (default `null`),
   **não** serializado em `toArray()`.
3. `FuzzyMatcher::rank` / `CommandRegistry::resolveFor` respeitam `rankScore` fixo.
4. Concern `HasGlobalSearch` na `Resource` base — o contrato público (2 métodos).
5. Registro do provider no `ArqelServiceProvider`.

## Arquitetura

### Novos artefatos (todos em `packages/core`, exceto o concern no pacote de resources)

| Artefato | Papel |
|---|---|
| `CommandPalette/Providers/RecordSearchCommandProvider.php` | Consulta o banco por resource buscável; emite `Command` por registro |
| `HasGlobalSearch` (concern na `Resource` base) | Contrato público: `globallySearchable()` + `globalSearchResultTitle()` |
| `Command::$rankScore` (campo novo, opcional) | Score fixo p/ registros; **não** vaza no JSON |
| `FuzzyMatcher::rank` (ajuste) | Respeita `rankScore` fixo: pula re-score, nunca descarta |
| `ArqelServiceProvider` (registro) | `registerProvider(new RecordSearchCommandProvider(...))` |

## Contrato público (congela no 1.0)

Vive no concern `HasGlobalSearch`, incluído na `Resource` base para manter a classe enxuta.

```php
/**
 * Attributes searched by the global command palette. Empty = opt-out
 * (default). Return column names on the resource's model table.
 *
 * @return array<int, string>
 */
public static function globallySearchable(): array
{
    return [];
}

/**
 * Human label for a record in the global search results. Defaults to
 * the value of the first globallySearchable() attribute, cast to string,
 * falling back to "#{id}" when empty. Override to compose a richer title.
 */
public static function globalSearchResultTitle(Model $record): string
{
    // default: (string) $record->{first-searchable-attr}; '' → "#{$record->getKey()}"
}
```

Dois métodos congelados:

- `globallySearchable(): array` — opt-in explícito, `[]` por default. **Segurança-por-default**:
  nenhum resource expõe registros sem o dono declarar os campos.
- `globalSearchResultTitle(Model $record): string` — override opcional; a convenção
  (1º campo) cobre o caso comum.

**Deliberadamente fora (YAGNI — aditivos pós-1.0 sem quebrar SemVer):**

- `globalSearchQuery(Builder, string)` (WHERE custom / full-text) — método novo opcional depois.
- subtitle / details multi-linha — método novo opcional depois.
- agrupamento por resource no React — aditivo depois.

Cada um é um método opcional novo; adicioná-lo não altera assinaturas existentes, então
o freeze do 1.0 permanece honesto.

## Fluxo de query

`RecordSearchCommandProvider::provide(?Authenticatable $user, string $query): array`,
espelhando o `NavigationCommandProvider`:

```
1. term = trim($query); if mb_strlen(term) < MIN_TERM_LENGTH (2) → return []
2. panelPath = resolvePanelPath()  (mesma lógica do NavigationCommandProvider)
3. escaped = addcslashes(term, '%_\\')   (LIKE literals)
4. para cada $resourceClass em ResourceRegistry::all():
     a. if ResourceAuthorization::viewAnyDenied($resourceClass, $user) → skip
     b. $attrs = $resourceClass::globallySearchable()   (try/catch → skip no erro)
     c. if $attrs === [] → skip
     d. $model = $resourceClass::getModel();
        $records = $model::query()
            ->where(function ($sub) use ($attrs, $escaped) {
                foreach ($attrs as $col) {
                    $sub->orWhere($col, 'LIKE', "%{$escaped}%");
                }
            })
            ->limit(PER_RESOURCE_LIMIT)   // 5
            ->get();
        (todo o bloco d. em try/catch → skip o resource no erro)
     e. para cada $record: $commands[] = buildCommand($resourceClass, $record, $panelPath)
5. return $commands   (cap global de 20 aplicado depois pelo FuzzyMatcher)
```

### `buildCommand`

- `id` = `"record:{slug}:{key}"`
- `label` = `$resourceClass::globalSearchResultTitle($record)`
- `url` = `"{panelPath}/{slug}/{key}/edit"`; para resource read-only (sem rota de edit)
  cai para `"{panelPath}/{slug}"`
- `category` = rótulo localizado tipo `palette.category.records` (ex: "Records")
- `icon` = `$resourceClass::getNavigationIcon()` (defensivo, `null` no erro)
- `rankScore` = `RECORD_RANK_SCORE` (60) — fixo

### Constantes

- `MIN_TERM_LENGTH = 2`
- `PER_RESOURCE_LIMIT = 5`
- `RECORD_RANK_SCORE = 60`

Constantes de classe, ajustáveis sem mudar assinatura.

## Convivência com FuzzyMatcher (abordagem A)

Hoje `FuzzyMatcher::rank` faz re-score de cada `Command` pelo label/description e
**descarta score 0**, com cap de 20. Problema: um registro que casou no SQL por um campo
que **não** é o título (ex: casou em `email`, título é `name`) receberia score 0 e seria
descartado — perdendo um resultado legítimo.

**Solução:** `Command` ganha `?int $rankScore` (default `null`). Em `FuzzyMatcher::rank`:

- Se `$command->rankScore !== null` → usa esse score fixo direto (pula `score()`, **nunca**
  descarta por 0).
- Se `null` → comportamento atual inalterado (fuzzy contra label/description).

Registros entram com `rankScore = 60`: abaixo de comandos exact/contains (95/80), acima de
subsequence fraco — navegação com match forte aparece antes, mas registros nunca somem.
Ordenação entre registros = ordem de chegada (sort estável por índice).

`Command::toArray()` **não** inclui `rankScore` (já omite os flags de auth hoje) — o
contrato JSON/React fica idêntico.

## Segurança

- **Autorização:** gate `viewAny` por resource (mesmo do sidebar/navegação, via
  `ResourceAuthorization::viewAnyDenied`). Registros individuais **não** passam por Policy
  `view` por-registro *neste provider* — decisão consciente: a busca só expõe título + link;
  o acesso real ao registro é gated pela Policy no controller de destino (edit). Documentado
  como decisão, não gap. (Aditivo futuro: filtro por-registro opcional.)
- **Opt-in:** `globallySearchable()` default `[]` — nenhum dado exposto sem declaração
  explícita do dono do resource.
- **SQL injection:** valores parametrizados pelo binding do Eloquent (`orWhere($col, 'LIKE', $bound)`).
  Nomes de coluna vêm de `globallySearchable()` (código do dono), nunca do request. `%`/`_`/`\`
  no termo são escapados (`addcslashes`) → tratados como literais.

## Erros & edge cases

| Caso | Comportamento |
|---|---|
| Query < 2 chars / só espaços | `return []` — não toca o banco |
| Resource sem `getModel()` / model inexistente | try/catch → skip esse resource, demais respondem |
| Coluna inexistente em `globallySearchable()` | QueryException capturada por-resource → skip |
| `%`, `_`, `\` no termo | escapados → literais |
| `viewAny` negado | resource pulado |
| Título vazio (1º campo null) | fallback `"#{$record->getKey()}"` |
| Resource read-only (sem edit) | URL = index do resource |
| Muitos resources buscáveis | cap global 20 do FuzzyMatcher; navegação (score alto) tem prioridade |
| Termo casa em campo não-título | registro entra via `rankScore` fixo — **não** descartado |

## Testes (Pest — rodáveis localmente)

**`RecordSearchCommandProviderTest`:**

- query < 2 chars → `[]` (não toca o banco)
- acha registros por LIKE em múltiplas colunas (`orWhere`)
- respeita `PER_RESOURCE_LIMIT` por resource
- pula resource com `globallySearchable() === []`
- pula resource com `viewAny` negado (`Gate::define`)
- termo com `%`/`_` tratado como literal
- title = 1º campo; override via `globalSearchResultTitle`
- fallback `#{id}` quando título vazio
- URL aponta ao edit; index para read-only
- resource que lança em `getModel()` é pulado, demais sobrevivem

**Regressão da abordagem A (`CommandRegistry` / `FuzzyMatcher`):**

- command com `rankScore` fixo **não** é descartado mesmo com label que daria score 0
- `toArray()` continua sem expor `rankScore`
- commands normais continuam fuzzy-ranqueados como antes (nenhuma regressão)

**Integração (endpoint):** `GET /admin/commands?q=ana` retorna registros + navegação juntos.

Cobertura-alvo: core PHP ≥90%. Nenhum teste JS novo (React intocado).

## Fora de escopo

- Notifications UI (feature seguinte do milestone 0.19 — spec própria).
- Full-text / scout drivers, subtitles, agrupamento no React (aditivos pós-1.0).
- Mudanças no `CommandPalette.tsx` ou no endpoint.
