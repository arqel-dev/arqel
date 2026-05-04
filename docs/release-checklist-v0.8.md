# Release Checklist — v0.8.0-rc.1

> Documento operacional para o maintainer cortar a primeira release candidate
> pública do Arqel. Implementa GOV-V2-001 (escopo reduzido) de
> `PLANNING/09-fase-2-essenciais.md` (linhas 5232–5255).
>
> **Performance benchmarks foram diferidos** — serão executados antes da
> tag estável `v0.8.0` (não para o RC).

---

## 0. Pré-requisitos do maintainer

- [ ] Acesso `npm` ao escopo `@arqel` (token 2FA-protected em `~/.npmrc`).
- [ ] Webhook Packagist configurado em `https://packagist.org/api/update-package`.
- [ ] GPG key registrada no GitHub (`git config user.signingkey` + `commit.gpgsign true`).
- [ ] Permissão de push em todos os 13 sub-repos `arqel-dev/*` no GitHub (criados via splitsh/lite).
- [ ] Smoke app `arqel-test` clonada localmente em `~/PhpstormProjects/arqel-test`.

---

## 1. Pre-flight (≈ 30 min antes da tag)

Rodar a partir da raiz do monorepo:

- [ ] `git status` — working tree limpa.
- [ ] `git pull --ff-only origin master` — atualizado com remote.
- [ ] `pnpm install --frozen-lockfile` — lockfile íntegro.
- [ ] `composer install --no-interaction` em cada `packages/*` (ou via script root, se existir).
- [ ] `pnpm test:all` — todos os testes JS + lint + typecheck verdes.
- [ ] `vendor/bin/pest --parallel` em cada pacote PHP — verde.
- [ ] `vendor/bin/phpstan analyse --memory-limit=2G` em cada pacote — `level: max` clean.
- [ ] `vendor/bin/pint --test` — Pint clean (sem diff pendente).
- [ ] `pnpm build` — todos os pacotes JS compilam sem erros.
- [x] **`php artisan arqel:doctor --strict`** — comando ampliado com checks
      `broadcasting.driver`, `queue.driver`, `ai.providers.configured` e
      `marketplace.migrations` (batch B53).
- [x] **`php artisan arqel:audit --strict`** — novo comando que valida
      monorepo readiness (SKILL.md presente, composer.json válido,
      CHANGELOG entry, layout canônico, suite size ≥ threshold)
      (batch B53).
- [ ] CI verde no último commit de `master` (consultar `gh run list --branch master --limit 1`).

**Critério de bloqueio:** qualquer falha aqui aborta o release. Investigar antes de prosseguir.

---

## 2. Version bumps

### 2.1 Pacotes Composer (`packages/*/composer.json`)

Sem ação — Composer infere versão do tag git via Packagist. **Nenhum `version`
key deve ser adicionado** a esses arquivos (ADR-004, evita drift).

Pacotes afetados (13):

- `arqel-dev/core`
- `arqel-dev/fields`
- `arqel-dev/fields-advanced`
- `arqel-dev/form`
- `arqel-dev/table`
- `arqel-dev/actions`
- `arqel-dev/auth`
- `arqel-dev/nav`
- `arqel-dev/audit`
- `arqel-dev/export`
- `arqel-dev/mcp`
- `arqel-dev/tenant`
- `arqel-dev/widgets`

### 2.2 Pacotes npm (`packages-js/*/package.json`)

Bump `"version"` para `"0.8.0-rc.1"` (já aplicado neste commit) em:

- [x] `packages-js/types/package.json` — `@arqel-dev/types`
- [x] `packages-js/react/package.json` — `@arqel-dev/react`
- [x] `packages-js/hooks/package.json` — `@arqel-dev/hooks`
- [x] `packages-js/ui/package.json` — `@arqel-dev/ui`
- [x] `packages-js/fields-js/package.json` — `@arqel-dev/fields`

> Cross-deps em `workspace:*` permanecem como estão — pnpm/npm publish
> resolverão para `0.8.0-rc.1` no momento do publish.

---

## 3. Changelog

- [ ] Abrir `CHANGELOG.md`.
- [ ] Renomear a seção `## [0.8.0-rc.1 — pendente de tag]` para
      `## [0.8.0-rc.1] - YYYY-MM-DD` (data ISO real do release).
- [ ] Verificar que toda a evolução desde 0.0.0 está listada (consolidar
      Batches paralelos #1–#16 em subseções `Added`/`Changed`/`Fixed`).
- [ ] Adicionar link de comparação no rodapé:
      `[0.8.0-rc.1]: https://github.com/arqel-dev/arqel/releases/tag/v0.8.0-rc.1`.
- [ ] Manter o placeholder `## [Unreleased]` vazio no topo para o próximo ciclo.

Commit dedicado:

```bash
git add CHANGELOG.md
git commit --signoff -m "docs(changelog): cut 0.8.0-rc.1"
```

---

## 4. Tag assinada

```bash
git tag -s v0.8.0-rc.1 -m "Release v0.8.0-rc.1"
git push origin v0.8.0-rc.1
```

- [ ] Tag aparece em `gh release list`.
- [ ] Tag tem assinatura GPG válida (`git tag -v v0.8.0-rc.1`).

---

## 5. Splitsh push para sub-repos

O monorepo é split-publicado para `github.com/arqel/<package>` via
`splitsh/lite`. Workflow `.github/workflows/release.yml` faz isso
automaticamente quando a tag `v*` é empurrada.

- [ ] Checar `gh run watch` no workflow disparado pela tag.
- [ ] Confirmar que cada sub-repo recebeu a tag `v0.8.0-rc.1`:
      `for pkg in core fields fields-advanced form table actions auth nav audit export mcp tenant widgets; do gh api repos/arqel/$pkg/git/refs/tags/v0.8.0-rc.1 --jq .ref; done`.

---

## 6. Packagist

- [ ] Webhook Packagist dispara automático ao push da tag.
- [ ] Verificar manualmente em `https://packagist.org/packages/arqel-dev/<pkg>`
      que a versão `0.8.0-rc.1` aparece (pode levar 1–2 min).
- [ ] Em caso de atraso, forçar update:
      `curl -XPOST -H'content-type:application/json' "https://packagist.org/api/update-package?username=arqel&apiToken=$PACKAGIST_TOKEN" -d'{"repository":{"url":"https://github.com/arqel/<pkg>"}}'`.

---

## 7. npm publish

Disparado pelo workflow `.github/workflows/release.yml` ao detectar tag
`v*`. Para cada pacote em `packages-js/*`, o workflow roda:

```bash
pnpm --filter @arqel-dev/<pkg> publish --tag rc --access public --no-git-checks
```

> `--tag rc` garante que `npm install @arqel-dev/ui` (sem flag) **não**
> instale o RC — só `@arqel-dev/ui@rc` ou `@arqel-dev/ui@0.8.0-rc.1` explícitos.

- [ ] Workflow `release.yml` verde.
- [ ] `npm view @arqel-dev/ui versions --json` lista `0.8.0-rc.1`.
- [ ] `npm view @arqel-dev/ui dist-tags` mostra `rc: 0.8.0-rc.1` e `latest`
      apontando para versão estável anterior (ou ausente, se for o primeiro).

Manual fallback (se workflow falhar):

```bash
pnpm -r --filter "./packages-js/*" publish --tag rc --access public
```

---

## 8. Smoke test em arqel-test

```bash
cd ~/PhpstormProjects/arqel-test
./scripts/setup-test-app.sh --version=0.8.0-rc.1
```

- [ ] `composer require arqel-dev/core:0.8.0-rc.1 --dev-master=false` — sem conflitos.
- [ ] `pnpm add @arqel-dev/ui@rc @arqel-dev/react@rc @arqel-dev/hooks@rc @arqel-dev/types@rc @arqel-dev/fields@rc` — instala.
- [ ] `pnpm dev` + `php artisan serve` — admin panel carrega em `http://localhost:8000/admin`.
- [ ] CRUD básico (create/read/update/delete) de uma `Resource` de exemplo funciona.
- [ ] Login + Policy flow funciona.
- [ ] Sem erros no console do navegador, sem `dd()`/`dump()` no log Laravel.

---

## 9. Anúncio público

- [ ] **GitHub Release**: `gh release create v0.8.0-rc.1 --prerelease --title "v0.8.0-rc.1" --notes-from-tag` — copia notas do CHANGELOG.
- [ ] **GitHub Discussions**: postar em `Announcements` linkando o release + pedindo feedback.
- [ ] **Twitter/X** `@arqel_dev`: thread curta — tagline + 3 highlights + link.
- [ ] **Reddit r/laravel**: post com tag `[Release]` — descrição + link Discussions.
- [ ] **Laravel News**: enviar via formulário `https://laravel-news.com/links/create`.
- [ ] **arqel.dev/blog**: publicar post `2026-XX-XX-v080-rc1.md` (deferido para release estável; opcional para RC).

---

## 10. Pós-release

- [ ] Atualizar `docs/tickets/current.md` para apontar próximo ticket.
- [ ] Bumpar `packages-js/*/package.json` para `0.8.1-dev` (próximo ciclo).
- [ ] Abrir issue `[META] v0.8.0 stable release tracking` listando bloqueadores.
- [ ] Configurar GitHub branch protection em `master` para exigir status `release-dry-run`.

---

## 11. Procedimento de rollback

Se smoke test (§8) falha **ou** bug crítico reportado nas primeiras 24h:

### 11.1 Despublicar do npm (janela de 72h)

```bash
for pkg in types react hooks ui fields; do
  npm unpublish @arqel-dev/$pkg@0.8.0-rc.1
done
```

> Após 72h, npm bloqueia unpublish. Use `npm deprecate` em vez disso:
>
> ```bash
> npm deprecate @arqel-dev/ui@0.8.0-rc.1 "Critical bug — use 0.8.0-rc.2"
> ```

### 11.2 Marcar tag como broken no GitHub

```bash
gh release edit v0.8.0-rc.1 --notes "**BROKEN — do not use.** See #<issue>. Fixed in v0.8.0-rc.2."
```

> **Não** deletar a tag git — quebra checkouts/lockfiles em apps que já
> instalaram. Sempre roll-forward com `rc.2`, nunca rewrite history.

### 11.3 Packagist

Não há ação direta — basta publicar `0.8.0-rc.2` corrigindo. Composer
respeita constraint `^0.8.0-rc.1` e pegará o sucessor.

### 11.4 Comunicação

- [ ] Editar GitHub Release com aviso (acima).
- [ ] Comentário pinned na Discussion de anúncio.
- [ ] Tweet de retração + apontamento para fix em progresso.
- [ ] Issue tracker: label `release-blocker` + `priority:critical`.

### 11.5 Postmortem

Após resolver, escrever postmortem curto em `docs/postmortems/YYYY-MM-DD-rc1.md`
listando: timeline, root cause, lições, mudanças no checklist.

---

## Referências

- `PLANNING/09-fase-2-essenciais.md` §GOV-V2-001 — spec original.
- `PLANNING/12-processos-qa.md` §3 — release process canônico.
- `docs/publishing-setup.md` — setup de tokens e webhooks.
- ADR-004 — versionamento sincronizado entre Composer e npm.
- ADR-008 — testes obrigatórios antes de release.
