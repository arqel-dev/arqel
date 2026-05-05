# Release Checklist

> Guia operacional para cortar uma release pública do Arqel.
> Documento de referência **público** — para o checklist interno
> com tracking de itens específicos do RC atual, ver
> `docs/release-checklist-v0.8.md` no repositório.

Este guia cobre os passos canônicos para qualquer release tag (rc, beta,
estável). Use junto com `arqel:doctor --strict` e `arqel:audit --strict`
para validar prontidão antes do tag.

---

## 1. Pre-flight

Rodar a partir da raiz do monorepo, na ordem:

- `git status` — working tree limpa.
- `git pull --ff-only origin master`.
- `pnpm install --frozen-lockfile`.
- `composer install --no-interaction` em cada `packages/*`.
- `vendor/bin/pest --parallel` em cada pacote PHP — verde.
- `vendor/bin/phpstan analyse --memory-limit=2G` — `level: max` clean.
- `vendor/bin/pint --test` — Pint clean.
- `pnpm test:all` — JS lint + tests + typecheck verdes.
- `pnpm build` — todos os pacotes JS compilam.
- `php artisan arqel:doctor --strict` — sem warns numa app de teste.
- `php artisan arqel:audit --strict` — monorepo readiness verde.
- CI verde no último commit de `master` (`gh run list --branch master --limit 1`).

**Critério de bloqueio:** qualquer falha aqui aborta o release.

---

## 2. Version bumps

### Composer (`packages/*/composer.json`)

Sem ação — Composer infere versão do tag git via Packagist.
**Nenhum `version` key deve ser adicionado** a esses arquivos
(ADR-004, evita drift).

### npm (`packages-js/*/package.json`)

Bump `"version"` para a versão alvo (ex: `"0.8.0-rc.1"`) em todos os
pacotes `@arqel-dev/*`. Cross-deps em `workspace:*` permanecem como estão —
pnpm/npm publish resolverão para a versão correta no momento do publish.

---

## 3. Changelog

- Renomear a seção `## [X.Y.Z — pendente de tag]` para
  `## [X.Y.Z] - YYYY-MM-DD` com a data ISO real do release.
- Verificar que toda a evolução desde a última tag está listada.
- Adicionar link de comparação no rodapé.
- Manter o placeholder `## [Unreleased]` vazio no topo para o próximo
  ciclo.

Commit dedicado:

```bash
git add CHANGELOG.md
git commit --signoff -m "docs(changelog): cut X.Y.Z"
```

---

## 4. Tag assinada

```bash
git tag -s vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

Verificar: `git tag -v vX.Y.Z` confirma assinatura GPG válida.

---

## 5. Splitsh para sub-repos

O monorepo é split-publicado para `github.com/arqel/<package>` via
`splitsh/lite`. O workflow `.github/workflows/release.yml` faz isso
automaticamente quando uma tag `v*` é empurrada.

Acompanhar via `gh run watch` no workflow disparado pela tag.

---

## 6. Packagist + npm publish

- **Packagist**: webhook dispara automático ao push da tag. Verificar
  manualmente em `https://packagist.org/packages/arqel-dev/<pkg>` que a
  versão aparece (1–2 min de propagação).
- **npm**: o workflow roda `pnpm --filter @arqel-dev/<pkg> publish --tag
  rc --access public --no-git-checks`. Para releases estáveis, omitir
  `--tag rc` (será publicado em `latest`).

---

## 7. Smoke test em app fresh

```bash
cd ~/PhpstormProjects/arqel-test
./scripts/setup-test-app.sh --version=X.Y.Z
```

Validar:

- `composer require arqel-dev/framework:X.Y.Z` sem conflitos (meta-package que já agrega core, auth, etc.).
- `php artisan arqel:install` regista provider/middleware e gera o scaffold.
- `pnpm add @arqel-dev/ui@X.Y.Z @arqel-dev/react@X.Y.Z ...` instala.
- `pnpm dev` + `php artisan serve` — admin panel carrega.
- CRUD básico (create/read/update/delete) numa Resource de exemplo
  funciona.
- Login + Policy flow funciona.
- Sem erros no console do navegador, sem `dd()`/`dump()` no log.

---

## 8. Anúncio público

- **GitHub Release**:
  `gh release create vX.Y.Z [--prerelease] --title "vX.Y.Z" --notes-from-tag`.
- **GitHub Discussions** em `Announcements`.
- Twitter/X, Reddit r/laravel, Laravel News, blog em `arqel.dev`.

---

## 9. Procedimento de rollback

Se o smoke test falha **ou** bug crítico reportado nas primeiras 24h:

### npm (janela de 72h)

```bash
npm unpublish @arqel-dev/<pkg>@X.Y.Z
```

Após 72h, npm bloqueia unpublish. Use `npm deprecate` em vez disso:

```bash
npm deprecate @arqel-dev/<pkg>@X.Y.Z "Critical bug — use X.Y.Z+1"
```

### GitHub Release

Editar a release com aviso `**BROKEN — do not use.** See #<issue>.
Fixed in vX.Y.Z+1.`. **Não deletar a tag git** — quebra
checkouts/lockfiles em apps que já instalaram. Sempre roll-forward
com versão sucessora, nunca rewrite history.

### Packagist

Não há ação direta — basta publicar versão sucessora corrigindo.
Composer respeita constraints e pegará o sucessor.

### Postmortem

Após resolver, escrever postmortem curto em
`docs/postmortems/YYYY-MM-DD-<release>.md` listando timeline, root
cause, lições e mudanças no checklist.

---

## Referências

- `PLANNING/12-processos-qa.md` §3 — release process canônico.
- ADR-004 — versionamento sincronizado entre Composer e npm.
- ADR-008 — testes obrigatórios antes de release.
- `apps/docs/guide/installation.md` — setup do consumidor.
