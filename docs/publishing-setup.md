# Setup de publicação — Arqel

> Tutorial passo-a-passo para criar todas as contas, configurar credenciais e publicar Arqel pela primeira vez. Lê este documento de cima a baixo na primeira execução; depois usa-o como referência.

**Audiência:** maintainer principal (Diogo). Inclui passos com cobrança financeira — todos sinalizados com 💰.

**Estado atual do repo (2026-04-29):**

- Distribuição planeada: Composer (`arqel/*` no Packagist), npm (`@arqel/*`), site `arqel.dev`.
- Workflows GitHub Actions já existem como **placeholders** (`release.yml`, `docs-deploy.yml`) — vão ser preenchidos quando os secrets correspondentes existirem.
- DCO bot ainda não instalado (GOV-003 ficou marcado pendente "App instalação pendente").
- Nenhum package publicado ainda. Tudo abaixo é setup inicial de greenfield.

---

## Sumário

1. [GitHub — organização + repos](#1-github--organização--repos)
2. [Domínio `arqel.dev`](#2-domínio-arqeldev)
3. [Packagist (Composer)](#3-packagist-composer)
4. [npm — escopo `@arqel`](#4-npm--escopo-arqel)
5. [Site de documentação](#5-site-de-documentação)
6. [Secrets em GitHub Actions](#6-secrets-em-github-actions)
7. [Splitsh — sub-repos read-only por package](#7-splitsh--sub-repos-read-only-por-package)
8. [DCO bot + branch protection](#8-dco-bot--branch-protection)
9. [Comunicação + presença](#9-comunicação--presença)
10. [Primeira release `v0.1.0`](#10-primeira-release-v010)
11. [Manutenção contínua](#11-manutenção-contínua)
12. [Custos consolidados](#12-custos-consolidados)

---

## 1. GitHub — organização + repos

**Por quê primeiro:** tudo o resto (npm, Packagist, splitsh, CI) depende da org GitHub `arqel` existir.

### 1.1 Criar a organização

1. Acede [github.com/organizations/new](https://github.com/organizations/new).
2. Nome: `arqel` (verifica disponibilidade — se ocupado, escolhe alternativa e atualiza `CLAUDE.md` § "Distribuição" + os configs `composer.json` em todos os pacotes).
3. Plano: **Free** está OK no início. Considerar **Team** ($4/user/mês 💰) só quando começares a ter colaboradores precisando branch protection avançado (Free também tem mas com limites).
4. Email de billing: usa um alias dedicado se possível (ex: `billing@arqel.dev` depois do passo 2).

### 1.2 Repo principal

1. Cria `github.com/arqel/arqel` — público, MIT.
2. Push do monorepo atual:
   ```bash
   git remote add origin git@github.com:arqel/arqel.git
   git push -u origin master
   git push -u origin main
   ```
3. Branch default: `main` (ou `master` — mantém o que já está; só não mudes a meio).

### 1.3 Repos para sub-pacotes (splitsh)

Cada package em `packages/*` e `packages-js/*` vai ter um repo read-only espelhado via splitsh. Cria-os já vazios:

- `arqel/core`, `arqel/panel`, `arqel/fields`, `arqel/table`, `arqel/form`, `arqel/actions`, `arqel/auth`, `arqel/nav`, `arqel/tenant`, `arqel/widgets`, `arqel/fields-advanced`, `arqel/audit`, `arqel/export`, `arqel/mcp`, `arqel/testing`
- `arqel/react`, `arqel/ui`, `arqel/hooks`, `arqel/types`

**Atalho:** usa o GitHub CLI:
```bash
for pkg in core panel fields table form actions auth nav tenant widgets fields-advanced audit export mcp testing; do
  gh repo create arqel/$pkg --public --description "Read-only mirror of arqel/arqel — packages/$pkg" --license mit
done
for pkg in react ui hooks types; do
  gh repo create arqel/$pkg --public --description "Read-only mirror of arqel/arqel — packages-js/$pkg" --license mit
done
```

**Importante:** todos esses repos têm um aviso no README a apontar para o monorepo (`This is a read-only split. Open issues / PRs at https://github.com/arqel/arqel`).

### 1.4 Personal Access Token (PAT)

Para os workflows de splitsh + release precisarem fazer push nos sub-repos, cria um PAT fine-grained:

1. [github.com/settings/tokens?type=beta](https://github.com/settings/tokens?type=beta) → Generate new token.
2. Resource owner: organização `arqel`.
3. Repository access: All repositories (ou todos os sub-pacotes).
4. Permissions:
   - Contents: Read & Write
   - Metadata: Read
5. Expiration: 1 ano (anota no calendário para renovar).
6. Guarda o token — vais colá-lo como secret no §6.

---

## 2. Domínio `arqel.dev`

**Custo:** ~$12/ano 💰. `.dev` é gerido pela Google e força HTTPS via HSTS preload — bom para a marca.

1. Compra em [Cloudflare Registrar](https://dash.cloudflare.com/?to=/:account/domains/register) (preço at-cost, sem markup) ou [Namecheap](https://www.namecheap.com).
2. **Recomendo Cloudflare** — DNS + CDN + free SSL no mesmo sítio.
3. Após compra, configura DNS:
   - `arqel.dev` → site de docs (passo 5)
   - `www.arqel.dev` → CNAME para `arqel.dev`
   - `docs.arqel.dev` → opcional, se separares marketing de docs
4. **MX records** (para receber email no domínio): usa um provider — opções:
   - **[Migadu](https://www.migadu.com)** $19/ano 💰 — barato, sem limite de aliases. Recomendado.
   - **Google Workspace** $7/user/mês 💰 — caro mas familiar.
   - **Cloudflare Email Routing** grátis, mas só forwarding (não envia).
5. Cria estes aliases assim que o email funcionar:
   - `security@arqel.dev` (já referenciado em SECURITY.md)
   - `hello@arqel.dev`
   - `billing@arqel.dev`
   - `noreply@arqel.dev`

---

## 3. Packagist (Composer)

**Custo:** grátis para packages públicos.

### 3.1 Conta + ligação ao GitHub

1. Acede [packagist.org/login/github](https://packagist.org/login/github) e autoriza com a tua conta GitHub (a mesma que é owner da org `arqel`).
2. Após login, vai a [packagist.org/packages/submit](https://packagist.org/packages/submit).

### 3.2 Vendor namespace

Packagist não tem "orgs" como o npm — o vendor `arqel/` fica reservado a partir do momento em que registas o **primeiro** package com esse prefixo. Submete já um:

1. Submit Repository: `https://github.com/arqel/core` (depois do splitsh ter feito o primeiro push, §7).
2. Após o primeiro package, todos os outros `arqel/*` ficam implicitamente teus (Packagist verifica via GitHub que tu controlas a org).

### 3.3 Auto-update via GitHub webhook

Quando submeteres cada package, Packagist oferece colar um webhook URL no GitHub do sub-repo. Configura-o para evitar ter de clicar "Update" manualmente em cada release.

Alternativa: gerar API token Packagist em [packagist.org/profile](https://packagist.org/profile) e usar `composer global require packagist/api` no workflow de release para fazer update programaticamente. Anota o token como `PACKAGIST_API_TOKEN` (passo 6).

### 3.4 Submeter os 18 packages

Faz após o primeiro splitsh push (§7). Repete o submit para cada sub-repo. Demorado (~20 min) mas é uma vez só.

---

## 4. npm — escopo `@arqel`

**Custo:** grátis para packages públicos no escopo de organização.

### 4.1 Conta pessoal npm

1. Cria conta em [npmjs.com/signup](https://www.npmjs.com/signup) com `hello@arqel.dev` (ou o email principal).
2. **Habilita 2FA** — `Account → Two-Factor Authentication → Authorization and Publishing`. Isto é mandatório para publicar (npm exige desde 2024).

### 4.2 Criar a org `@arqel`

1. [npmjs.com/org/create](https://www.npmjs.com/org/create) → escolhe **Free** (público).
2. Nome: `arqel`. Confirma que está livre — se não estiver, é a mesma decisão que fizeste no §1.1 (escolher alternativa e atualizar tudo).
3. Após criada, todos os packages publicados como `@arqel/<nome>` ficam na org.

### 4.3 Granular Access Token para CI

Para o workflow `release.yml` publicar sem 2FA interativo:

1. [npmjs.com/settings/<user>/tokens](https://www.npmjs.com/settings/) → **Generate New Token → Granular Access Token**.
2. Permissions:
   - Packages and scopes: `@arqel` → Read and write.
3. Expiration: 1 ano.
4. Allowed IP ranges: deixa vazio (GitHub Actions runners têm IPs dinâmicos).
5. Guarda o token — vai como `NPM_TOKEN` no §6.

**Nota:** se um dia migrares para Trusted Publishers (OIDC) — npm suporta desde 2024 — podes evitar tokens de longa duração. Para v0.1 fica com token; é mais simples.

### 4.4 Verificar package names disponíveis

Antes de publicar a primeira vez, confere que cada nome em `packages-js/*` está livre:

```bash
for pkg in react ui hooks types; do
  npm view @arqel/$pkg 2>&1 | head -1
done
```

Cada um deve dizer "404 Not Found" (livre). Se algum estiver ocupado, é colisão de squat ou outro projeto com o mesmo nome no mesmo escopo (improvável já que a org é nova).

---

## 5. Site de documentação

**Recomendação:** [VitePress](https://vitepress.dev) ou [Astro Starlight](https://starlight.astro.build) deployed em **Cloudflare Pages** ou **Vercel**. Ambos têm tier free generoso.

### 5.1 Escolha de stack

| Stack | Prós | Contras |
|---|---|---|
| **VitePress** | Markdown puro, sintaxe `:::`, tema dark/light built-in, pesquisa via Algolia DocSearch | Tema menos polido out-of-box |
| **Astro Starlight** | Mais polido visualmente, componentes ricos, search local incluído | Mais complexo se não conheces Astro |
| **Mintlify** | UI premium, AI search incluído | $50/mês 💰 acima do tier grátis (que é limitado) |

**Recomendo:** **Starlight** — search local sem Algolia, design polido, componentes React funcionam (e tu já dominas o ecossistema React).

### 5.2 Estrutura

```
apps/docs/                  # ou repo separado arqel/arqel.dev
├── astro.config.mjs
├── src/
│   ├── content/docs/
│   │   ├── index.mdx       # landing
│   │   ├── getting-started/
│   │   ├── packages/       # 1 página por package
│   │   ├── adrs/           # mirror de PLANNING/03-adrs.md
│   │   └── recipes/
└── package.json
```

Os `SKILL.md` PT-BR de cada package podem ser fonte direta — basta um script que copia/transforma para `src/content/docs/packages/<nome>.md`.

### 5.3 Hosting

**Cloudflare Pages** (recomendado):

1. Login em [pages.cloudflare.com](https://pages.cloudflare.com).
2. Connect GitHub → autoriza repo `arqel/arqel`.
3. Build settings:
   - Build command: `pnpm --filter @arqel/docs build`
   - Build output: `apps/docs/dist`
   - Root directory: `/` (monorepo aware)
   - Node: 20
4. Custom domain: liga `arqel.dev` (o DNS já está no Cloudflare do passo 2 — UI guia-te).
5. **Free tier:** 500 builds/mês, bandwidth ilimitado. Suficiente.

**Alternativa Vercel:** `vercel.com` → import repo, mesmas settings. Free tier mais limitado em bandwidth (100 GB/mês) mas DX superior.

### 5.4 Search

- **Starlight built-in (Pagefind):** zero config, indexa em build time, grátis.
- **Algolia DocSearch:** programa free para projetos open-source — candidata-te em [docsearch.algolia.com/apply](https://docsearch.algolia.com/apply). Demora 1-2 semanas a ser aprovado.

### 5.5 Workflow `docs-deploy.yml`

O placeholder atual só faz `echo`. Quando o site existir, substitui por:

```yaml
- uses: pnpm/action-setup@v4
- uses: actions/setup-node@v4
  with: { node-version: 20, cache: pnpm }
- run: pnpm install --frozen-lockfile
- run: pnpm --filter @arqel/docs build
- uses: cloudflare/pages-action@v1
  with:
    apiToken: ${{ secrets.CLOUDFLARE_API_TOKEN }}
    accountId: ${{ secrets.CLOUDFLARE_ACCOUNT_ID }}
    projectName: arqel-docs
    directory: apps/docs/dist
```

Os 2 secrets vêm do passo 6.

---

## 6. Secrets em GitHub Actions

Todos em `Settings → Secrets and variables → Actions → New repository secret` no repo `arqel/arqel`.

| Secret | Origem | Usado em |
|---|---|---|
| `NPM_TOKEN` | §4.3 | `release.yml` |
| `PACKAGIST_API_TOKEN` | §3.3 | `release.yml` (auto-update) |
| `SPLITSH_PAT` | §1.4 | `release.yml` (push aos sub-repos) |
| `CLOUDFLARE_API_TOKEN` | Cloudflare → My Profile → API Tokens → Custom token (Pages: Edit) | `docs-deploy.yml` |
| `CLOUDFLARE_ACCOUNT_ID` | Cloudflare dashboard sidebar | `docs-deploy.yml` |
| `ALGOLIA_API_KEY` (opcional) | DocSearch após aprovação | build do site |

**Boa prática:** rotaciona PATs e tokens a cada 12 meses. Cria evento no calendário.

---

## 7. Splitsh — sub-repos read-only por package

Splitsh é a ferramenta canónica para monorepos PHP-style (Symfony, Laravel usam): publica cada subdiretório como um repo Git autónomo, preservando histórico.

### 7.1 Instalar `splitsh/lite` no runner

No `release.yml`, antes do step de splitsh:

```yaml
- name: Install splitsh/lite
  run: |
    curl -L https://github.com/splitsh/lite/releases/download/v2.0.0/lite_linux_amd64.tar.gz | tar xz
    sudo mv splitsh-lite /usr/local/bin/
```

### 7.2 Script de split

`scripts/split.sh` (criar — não existe ainda):

```bash
#!/usr/bin/env bash
set -e
TAG="$1"  # ex: v0.1.0

PACKAGES_PHP=(core panel fields table form actions auth nav tenant widgets fields-advanced audit export mcp testing)
PACKAGES_JS=(react ui hooks types)

split_and_push() {
  local prefix="$1"   # packages/core
  local repo="$2"     # arqel/core

  local sha
  sha=$(splitsh-lite --prefix="$prefix")
  git push "https://x-access-token:$SPLITSH_PAT@github.com/$repo.git" "$sha:refs/heads/main"
  git push "https://x-access-token:$SPLITSH_PAT@github.com/$repo.git" "$sha:refs/tags/$TAG"
}

for pkg in "${PACKAGES_PHP[@]}"; do
  split_and_push "packages/$pkg" "arqel/$pkg"
done
for pkg in "${PACKAGES_JS[@]}"; do
  split_and_push "packages-js/$pkg" "arqel/$pkg"
done
```

### 7.3 Wiring em `release.yml`

```yaml
- name: Split and push to sub-repos
  env:
    SPLITSH_PAT: ${{ secrets.SPLITSH_PAT }}
  run: bash scripts/split.sh ${{ github.ref_name }}
```

### 7.4 Primeira corrida (manual, antes do release)

Antes do primeiro `git tag v0.1.0`, faz uma corrida manual em local para os repos vazios receberem o histórico inicial:

```bash
git fetch --tags
SPLITSH_PAT=<token> bash scripts/split.sh main  # tag dummy "main"
```

Depois da corrida manual, **submete cada package no Packagist** (§3.4).

---

## 8. DCO bot + branch protection

### 8.1 Instalar DCO bot

1. Acede [github.com/apps/dco](https://github.com/apps/dco) → Install.
2. Escolhe a org `arqel` → All repositories.
3. Pronto. O bot bloqueia PRs sem `Signed-off-by:` no commit (já configurado no projeto via `--signoff` em todos os commits do monorepo).

### 8.2 Branch protection em `main`

`Settings → Branches → Add rule` no `arqel/arqel`:

- Branch name pattern: `main` (e `master` se for a default).
- ✅ Require pull request before merging
- ✅ Require approvals: 1 (no início, podes auto-mergear como solo maintainer)
- ✅ Require status checks to pass:
  - `ci`, `test-matrix (php-8.3, laravel-12)`, `test-matrix (php-8.4, laravel-13)`, `security`, `DCO`
- ✅ Require linear history
- ✅ Do not allow bypassing (mesmo para admins, depois de estabilizar)

### 8.3 CODEOWNERS

Cria `.github/CODEOWNERS`:

```
*                    @diogocoutinho
/PLANNING/           @diogocoutinho
/packages/core/      @diogocoutinho
/.github/            @diogocoutinho
```

Garante que tu reviês mudanças sensíveis. Adiciona maintainers à medida que entram.

---

## 9. Comunicação + presença

Setup leve para receber feedback antes do release:

- **GitHub Discussions** em `arqel/arqel` — `Settings → Features → Discussions ✅`. Categorias: Q&A, Ideas, Show and tell, Announcements.
- **Discord ou Slack** (opcional, fase 2): Discord é mais comum para OSS. Server grátis. Cria 3 canais: `#general`, `#help`, `#contributors`.
- **Twitter/X + Bluesky:** handle `@arqel_dev` em ambos. Vai sendo construído ao longo das releases.
- **README badges:** após release v0.1.0, adiciona em `README.md`:
  - `![Packagist](https://img.shields.io/packagist/v/arqel/core)`
  - `![npm](https://img.shields.io/npm/v/@arqel/ui)`
  - `![CI](https://github.com/arqel/arqel/actions/workflows/ci.yml/badge.svg)`
  - `![License](https://img.shields.io/badge/license-MIT-blue)`

---

## 10. Primeira release `v0.1.0`

Checklist sequencial. Não saltar steps.

### Pré-flight

- [ ] Todos os pacotes têm `composer.json` com `"version"` ausente (versão é derivada da tag) e `"name"` correto.
- [ ] Todos os `package.json` em `packages-js/*` têm `"version": "0.1.0"` e `"publishConfig": { "access": "public" }`.
- [ ] `CHANGELOG.md` tem secção `[0.1.0] — 2026-XX-XX`.
- [ ] CI verde em `main`.
- [ ] `pnpm build` produz dist limpo em todos os pacotes JS.
- [ ] `composer validate` passa em todos os pacotes PHP.

### Execução

1. **Tag local:**
   ```bash
   git checkout main && git pull
   git tag -s v0.1.0 -m "Release v0.1.0"
   git push origin v0.1.0
   ```
2. **GitHub Actions** despara `release.yml` automaticamente. Acompanha em `arqel/arqel/actions`.
3. Workflow vai:
   - Correr `splitsh-lite` e push para os 18 sub-repos com a tag `v0.1.0`.
   - Notificar Packagist via webhook (sub-repos publicam-se sozinhos com a tag).
   - `pnpm publish --recursive --access public --no-git-checks` para publicar todos os JS no npm.
4. **Verifica:**
   - [packagist.org/packages/arqel](https://packagist.org/packages/arqel) — todos os 15 PHP packages a 0.1.0.
   - [npmjs.com/org/arqel](https://www.npmjs.com/org/arqel) — 4 JS packages a 0.1.0.
5. **Smoke test em projeto fresh:**
   ```bash
   composer create-project laravel/laravel test-arqel
   cd test-arqel
   composer require arqel/core
   pnpm add @arqel/ui @arqel/react
   ```
   Confirma que tudo resolve sem path repos.

### Pós-release

- [ ] Cria GitHub Release a partir da tag (UI ou `gh release create v0.1.0 --notes-from-tag`).
- [ ] Anuncia em GitHub Discussions, Twitter, Reddit r/laravel.
- [ ] Atualiza `docs/tickets/current.md` para próxima fase.

---

## 11. Manutenção contínua

### Para cada release subsequente (v0.1.1, v0.2.0, …)

1. PRs mergam em `main` com Conventional Commits.
2. Quando há changes acumuladas suficientes:
   ```bash
   git tag -s vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```
3. CI faz o resto.

### SemVer policy

- `0.x.y` — breaking permitido em minor (0.1 → 0.2). Documenta em CHANGELOG.
- `1.0.0` — após N releases estáveis + feedback de produção. A partir daí: breaking só em major.

### Automação opcional

- **release-please** (Google): gera CHANGELOG + version bumps automáticos a partir dos Conventional Commits. Reduz fricção mas adiciona complexidade. Avalia depois de 5+ releases manuais.
- **Renovate** já está configurado (INFRA-005) — mantém deps atualizadas sem intervenção.

---

## 12. Custos consolidados

| Item | Custo | Recorrência |
|---|---|---|
| GitHub Free org | $0 | — |
| Domínio `.dev` (Cloudflare) | ~$12 | anual |
| Email (Migadu) | $19 | anual |
| Cloudflare Pages | $0 | — |
| Packagist | $0 | — |
| npm org Free | $0 | — |
| DCO bot | $0 | — |
| **Total mínimo** | **~$31** | **anual** |

**Opcionais que dobram o custo (não fazer no v0.1):**

- GitHub Team org: $4/user/mês
- Mintlify docs: $50/mês
- Algolia DocSearch: grátis para OSS aprovado
- Vercel Pro: $20/mês

---

## Apêndice A — Nomes alternativos se `arqel` estiver ocupado

Verifica antes de criar tudo:

```bash
# GitHub
curl -s https://api.github.com/users/arqel | grep -q "Not Found" && echo "GitHub livre" || echo "GitHub OCUPADO"

# npm
npm view @arqel 2>&1 | grep -q "404" && echo "npm livre" || echo "npm OCUPADO"

# Packagist (não tem API simples — visita https://packagist.org/packages/arqel)
```

Se ocupado, alternativas a considerar (manter sonoridade): `arqelio`, `arqel-dev`, `arqelpanel`. Mudar exige update em ~30 ficheiros (`composer.json` × 15, `package.json` × 4, namespaces, docs).

---

## Apêndice B — Order of operations resumida

1. Verificar `arqel` livre em GitHub + npm + Packagist (Apêndice A).
2. Comprar domínio + setup email (§2).
3. Criar GitHub org + repo principal + sub-repos vazios (§1).
4. Criar contas npm + Packagist + Cloudflare (§3, §4, §5.3).
5. Configurar todos os secrets em GitHub Actions (§6).
6. Instalar DCO bot + branch protection (§8).
7. Setup do site de docs (§5).
8. Primeira corrida manual de splitsh (§7.4) + submeter packages no Packagist.
9. Tag `v0.1.0` → release automatizado (§10).
10. Anúncio público.

Tempo total estimado: **2 dias úteis** para fazer tudo end-to-end (assumindo que esperas 1-2 semanas pela aprovação Algolia, mas isso não bloqueia o release).
