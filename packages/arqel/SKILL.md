# SKILL.md — arqel-dev/arqel

> Contexto canónico para AI agents. Este é o **meta-package** do framework Arqel — não contém código, apenas declara o stack completo. Estrutura conforme [`PLANNING/04-repo-structure.md`](../../PLANNING/04-repo-structure.md) §11.

## Purpose

`arqel-dev/arqel` é o **único requirement** que uma app Laravel precisa para entrar no ecossistema Arqel. Em vez do utilizador final ter de fazer `composer require` de 7 pacotes individuais + `inertiajs/inertia-laravel`, basta:

```bash
composer require arqel-dev/framework
```

…e o Composer puxa transitivamente o stack completo.

**Pacotes incluídos** (todos pinned em `self.version` para garantir versões alinhadas):

- `arqel-dev/core` — panels, resources, polymorphic routes, Inertia middleware, command palette, telemetry, install command
- `arqel-dev/auth` — authorization (policies discovery, ability registry) + páginas Inertia opt-in (login/register/forgot/reset/verify-email)
- `arqel-dev/fields` — schema de field types (text, number, select, dateTime, toggle, …)
- `arqel-dev/form` — renderização server-side de forms + validation rules extractor
- `arqel-dev/actions` — contratos e invokers de actions (bulk, table, header)
- `arqel-dev/nav` — navigation builder
- `arqel-dev/table` — table query/sort/filter/paginate
- `inertiajs/inertia-laravel` — peer obrigatório (única bridge PHP↔React, ADR-001)

## Key Contracts

**Sem código próprio.** O `composer.json` declara `"type": "metapackage"` — o Composer reconhece este tipo e instala apenas as dependências, sem criar uma pasta `vendor/arqel-dev/arqel/`. Não há `src/`, `tests/`, autoload, nem service provider neste pacote.

A versão é mantida em sincronia com os sub-pacotes via `self.version` no `require`. Quando o monorepo é tagged (e.g. `v0.8.0`), splitsh/lite propaga a tag a todos os repos públicos e o resolver vê todos os pacotes na mesma versão.

## Conventions

- **Apps user-land (downstream)** devem requerer **só** `arqel-dev/arqel` na sua composer require list. Não listar `arqel-dev/core`, `arqel-dev/auth`, etc. directamente — deixa o meta-package ser a fonte canónica de truth.
- **Workflows internos do monorepo** (CI, splitsh, testes cross-package, exemplos `examples/*`) continuam a fazer require dos sub-pacotes individuais via path repositories — assim mudanças locais são vistas imediatamente sem ter de bumpar `self.version` a cada commit.
- **Pacotes downstream** (e.g. um plugin community `acme/arqel-charts` que depende só de `core` e `fields`) devem requerer **os sub-pacotes específicos**, não o meta-package — caso contrário arrastam o stack inteiro e ficam acoplados a versões que talvez não precisem.
- **Versionamento:** o meta-package segue o mesmo SemVer do monorepo. Bumps são automáticos no release pipeline (INFRA-004).

## Examples

### Setup zero-touch numa Laravel app limpa

```bash
# 1. Adicionar Arqel
composer require arqel-dev/framework

# 2. Bootstrap (idempotente — pode ser corrido várias vezes com --force)
php artisan arqel:install

# 3. Rodar migrations (a tabela users do Laravel default já chega para o starter)
php artisan migrate

# 4. Criar o primeiro admin (Filament-style — interactivo se sem flags)
php artisan arqel:make-user
```

Após estes 4 comandos, `/admin/login` está activo, autentica via guard default e redirecciona para `/admin` que mostra o `UserResource` scaffolded.

### composer.json mínimo de uma app Arqel

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "arqel-dev/arqel": "^0.8"
    }
}
```

## Anti-patterns

- ❌ **Requerer `arqel-dev/arqel` em pacotes downstream** (plugins, addons community). Cada plugin deve fazer require apenas dos sub-pacotes que realmente usa — caso contrário um plugin que só toca em fields faz cascade upgrade do core/auth/table/etc. quando bumpas o meta-package.
- ❌ **Editar `composer.json` deste pacote para apontar para versões desalinhadas** (`"arqel-dev/core": "^0.7", "arqel-dev/auth": "^0.8"`). O contrato é que **tudo** está em `self.version` — desalinhar quebra a invariante "stack monolítico tagged-as-one".
- ❌ **Adicionar `require-dev`, `autoload`, `scripts` ou outras secções não-meta** ao `composer.json`. `type: metapackage` exige que só `name`, `description`, `require`, `keywords`, `license`, `authors`, `support`, `homepage`, `minimum-stability`, `prefer-stable` apareçam.
- ❌ **Criar uma pasta `src/` neste pacote** "para conveniência". Se um helper precisa existir, vai para `arqel-dev/core` (ou para um pacote novo discutido em ADR).

## Related

- Repo structure: [`PLANNING/04-repo-structure.md`](../../PLANNING/04-repo-structure.md)
- Sub-package canónico: [`packages/core/SKILL.md`](../core/SKILL.md)
- README top-level: [`README.md`](../../README.md)
- Roadmap: [`PLANNING/07-roadmap-fases.md`](../../PLANNING/07-roadmap-fases.md)
- ADRs relevantes:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia como única bridge PHP↔React
  - [ADR-018](../../PLANNING/03-adrs.md) — Service Provider auto-discovery
