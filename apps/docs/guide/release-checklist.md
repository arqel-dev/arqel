# Release Checklist

> Operational guide for cutting a public Arqel release.
> **Public** reference document — for the internal checklist
> with tracking of items specific to the current RC, see
> `docs/release-checklist-v0.8.md` in the repo.

This guide covers the canonical steps for any release tag (rc, beta,
stable). Use it together with `arqel:doctor --strict` and `arqel:audit --strict`
to validate readiness before tagging.

---

## 1. Pre-flight

Run from the monorepo root, in this order:

- `git status` — clean working tree.
- `git pull --ff-only origin master`.
- `pnpm install --frozen-lockfile`.
- `composer install --no-interaction` in each `packages/*`.
- `vendor/bin/pest --parallel` in each PHP package — green.
- `vendor/bin/phpstan analyse --memory-limit=2G` — `level: max` clean.
- `vendor/bin/pint --test` — Pint clean.
- `pnpm test:all` — JS lint + tests + typecheck green.
- `pnpm build` — every JS package compiles.
- `php artisan arqel:doctor --strict` — no warnings on a test app.
- `php artisan arqel:audit --strict` — monorepo readiness green.
- CI green on the latest `master` commit (`gh run list --branch master --limit 1`).

**Blocking criterion:** any failure here aborts the release.

---

## 2. Version bumps

### Composer (`packages/*/composer.json`)

No action — Composer infers the version from the git tag via Packagist.
**No `version` key should be added** to those files
(ADR-004, avoids drift).

### npm (`packages-js/*/package.json`)

Bump `"version"` to the target version (e.g. `"0.8.0-rc.1"`) in every
`@arqel-dev/*` package. Cross-deps in `workspace:*` stay as is —
pnpm/npm publish will resolve to the right version at publish time.

---

## 3. Changelog

- Rename the `## [X.Y.Z — pending tag]` section to
  `## [X.Y.Z] - YYYY-MM-DD` with the actual ISO release date.
- Verify that all evolution since the last tag is listed.
- Add a comparison link in the footer.
- Keep the `## [Unreleased]` placeholder empty at the top for the next
  cycle.

Dedicated commit:

```bash
git add CHANGELOG.md
git commit --signoff -m "docs(changelog): cut X.Y.Z"
```

---

## 4. Signed tag

```bash
git tag -s vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

Verify: `git tag -v vX.Y.Z` confirms a valid GPG signature.

---

## 5. Splitsh for sub-repos

The monorepo is split-published to `github.com/arqel/<package>` via
`splitsh/lite`. The `.github/workflows/release.yml` workflow does this
automatically when a `v*` tag is pushed.

Track via `gh run watch` on the workflow triggered by the tag.

---

## 6. Packagist + npm publish

- **Packagist**: webhook fires automatically on tag push. Verify
  manually at `https://packagist.org/packages/arqel-dev/<pkg>` that the
  version appears (1–2 min propagation).
- **npm**: the workflow runs `pnpm --filter @arqel-dev/<pkg> publish --tag
  rc --access public --no-git-checks`. For stable releases, omit
  `--tag rc` (it will be published to `latest`).

---

## 7. Smoke test on a fresh app

```bash
cd ~/PhpstormProjects/arqel-test
./scripts/setup-test-app.sh --version=X.Y.Z
```

Validate:

- `composer require arqel-dev/framework:X.Y.Z` with no conflicts (the meta-package already aggregates core, auth, etc.).
- `php artisan arqel:install` registers the provider/middleware and generates the scaffold.
- `pnpm add @arqel-dev/ui@X.Y.Z @arqel-dev/react@X.Y.Z ...` installs.
- `pnpm dev` + `php artisan serve` — admin panel loads.
- Basic CRUD (create/read/update/delete) on a sample Resource works.
- Login + Policy flow works.
- No errors in the browser console, no `dd()`/`dump()` in the log.

---

## 8. Public announcement

- **GitHub Release**:
  `gh release create vX.Y.Z [--prerelease] --title "vX.Y.Z" --notes-from-tag`.
- **GitHub Discussions** in `Announcements`.
- Twitter/X, Reddit r/laravel, Laravel News, blog at `arqel.dev`.

---

## 9. Rollback procedure

If smoke test fails **or** a critical bug is reported in the first 24h:

### npm (72h window)

```bash
npm unpublish @arqel-dev/<pkg>@X.Y.Z
```

After 72h, npm blocks unpublish. Use `npm deprecate` instead:

```bash
npm deprecate @arqel-dev/<pkg>@X.Y.Z "Critical bug — use X.Y.Z+1"
```

### GitHub Release

Edit the release with a `**BROKEN — do not use.** See #<issue>.
Fixed in vX.Y.Z+1.` notice. **Don't delete the git tag** — that breaks
checkouts/lockfiles in apps that already installed. Always roll-forward
with a successor version, never rewrite history.

### Packagist

No direct action — just publish a successor version with the fix.
Composer respects constraints and will pick up the successor.

### Postmortem

After resolving, write a short postmortem in
`docs/postmortems/YYYY-MM-DD-<release>.md` listing timeline, root
cause, lessons, and changes to the checklist.

---

## References

- `PLANNING/12-processos-qa.md` §3 — canonical release process.
- ADR-004 — synchronized versioning between Composer and npm.
- ADR-008 — required tests before release.
- `apps/docs/guide/installation.md` — consumer setup.
