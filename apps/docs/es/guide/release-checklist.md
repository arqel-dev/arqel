# Release Checklist

> Guía operacional para cortar una release pública de Arqel.
> Documento de referencia **público** — para el checklist interno
> con el seguimiento de items específicos del RC actual, ver
> `docs/release-checklist-v0.8.md` en el repo.

Esta guía cubre los pasos canónicos para cualquier tag de release (rc, beta,
estable). Úsala junto con `arqel:doctor --strict` y `arqel:audit --strict`
para validar el readiness antes de taguear.

---

## 1. Pre-flight

Corre desde la raíz del monorepo, en este orden:

- `git status` — working tree limpio.
- `git pull --ff-only origin master`.
- `pnpm install --frozen-lockfile`.
- `composer install --no-interaction` en cada `packages/*`.
- `vendor/bin/pest --parallel` en cada paquete PHP — verde.
- `vendor/bin/phpstan analyse --memory-limit=2G` — `level: max` limpio.
- `vendor/bin/pint --test` — Pint limpio.
- `pnpm test:all` — JS lint + tests + typecheck verdes.
- `pnpm build` — cada paquete JS compila.
- `php artisan arqel:doctor --strict` — sin warnings en una app de test.
- `php artisan arqel:audit --strict` — readiness del monorepo verde.
- CI verde en el último commit de `master` (`gh run list --branch master --limit 1`).

**Criterio bloqueante:** cualquier fallo aquí aborta la release.

---

## 2. Bumps de versión

### Composer (`packages/*/composer.json`)

Sin acción — Composer infiere la versión desde el tag git vía Packagist.
**No debe añadirse la key `version`** a esos archivos
(ADR-004, evita drift).

### npm (`packages-js/*/package.json`)

Sube `"version"` a la versión target (e.g. `"0.8.0-rc.1"`) en cada
paquete `@arqel-dev/*`. Las cross-deps en `workspace:*` se quedan tal cual —
pnpm/npm publish resolverán a la versión correcta en el momento del publish.

---

## 3. Changelog

- Renombra la sección `## [X.Y.Z — pending tag]` a
  `## [X.Y.Z] - YYYY-MM-DD` con la fecha ISO real de la release.
- Verifica que toda la evolución desde el último tag esté listada.
- Añade un link de comparación en el footer.
- Mantén el placeholder `## [Unreleased]` vacío arriba para el siguiente
  ciclo.

Commit dedicado:

```bash
git add CHANGELOG.md
git commit --signoff -m "docs(changelog): cut X.Y.Z"
```

---

## 4. Tag firmado

```bash
git tag -s vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

Verifica: `git tag -v vX.Y.Z` confirma una firma GPG válida.

---

## 5. Splitsh para sub-repos

El monorepo se split-publica a `github.com/arqel/<package>` vía
`splitsh/lite`. El workflow `.github/workflows/release.yml` lo hace
automáticamente cuando se pushea un tag `v*`.

Sigue con `gh run watch` el workflow disparado por el tag.

---

## 6. Publish a Packagist + npm

- **Packagist**: el webhook se dispara automáticamente al pushear el tag. Verifica
  manualmente en `https://packagist.org/packages/arqel-dev/<pkg>` que la
  versión aparece (1–2 min de propagación).
- **npm**: el workflow corre `pnpm --filter @arqel-dev/<pkg> publish --tag
  rc --access public --no-git-checks`. Para releases estables, omite
  `--tag rc` (se publicará a `latest`).

---

## 7. Smoke test en una app nueva

```bash
cd ~/PhpstormProjects/arqel-test
./scripts/setup-test-app.sh --version=X.Y.Z
```

Valida:

- `composer require arqel-dev/framework:X.Y.Z` sin conflictos (el meta-paquete ya agrega core, auth, etc.).
- `php artisan arqel:install` registra el provider/middleware y genera el scaffold.
- `pnpm add @arqel-dev/ui@X.Y.Z @arqel-dev/react@X.Y.Z ...` instala.
- `pnpm dev` + `php artisan serve` — el admin panel carga.
- CRUD básico (create/read/update/delete) en un Resource de muestra funciona.
- Flujo login + Policy funciona.
- Sin errores en la consola del browser, sin `dd()`/`dump()` en el log.

---

## 8. Anuncio público

- **GitHub Release**:
  `gh release create vX.Y.Z [--prerelease] --title "vX.Y.Z" --notes-from-tag`.
- **GitHub Discussions** en `Announcements`.
- Twitter/X, Reddit r/laravel, Laravel News, blog en `arqel.dev`.

---

## 9. Procedimiento de rollback

Si el smoke test falla **o** un bug crítico se reporta en las primeras 24h:

### npm (ventana de 72h)

```bash
npm unpublish @arqel-dev/<pkg>@X.Y.Z
```

Tras 72h, npm bloquea el unpublish. Usa `npm deprecate` en su lugar:

```bash
npm deprecate @arqel-dev/<pkg>@X.Y.Z "Critical bug — use X.Y.Z+1"
```

### GitHub Release

Edita la release con un aviso `**BROKEN — do not use.** See #<issue>.
Fixed in vX.Y.Z+1.`. **No borres el tag git** — eso rompe
checkouts/lockfiles en apps que ya instalaron. Siempre haz roll-forward
con una versión sucesora, nunca reescribas el historial.

### Packagist

Sin acción directa — solo publica una versión sucesora con el fix.
Composer respeta las constraints y tomará la sucesora.

### Postmortem

Tras resolver, escribe un postmortem corto en
`docs/postmortems/YYYY-MM-DD-<release>.md` listando timeline, root
cause, lecciones y cambios al checklist.

---

## Referencias

- `PLANNING/12-processos-qa.md` §3 — proceso de release canónico.
- ADR-004 — versionado sincronizado entre Composer y npm.
- ADR-008 — tests requeridos antes de release.
- `apps/docs/guide/installation.md` — setup del consumer.
