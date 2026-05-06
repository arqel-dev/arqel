#!/usr/bin/env node
/**
 * Copies monorepo resources into the package so the published tarball ships
 * a self-contained set of inputs:
 *
 *   - `apps/docs`  → `<package>/docs/`     (corpus searched by `search_docs`)
 *   - `PLANNING/03-adrs.md` → `<package>/planning/03-adrs.md` (used by `get_adr`)
 *   - `PLANNING/05-api-php.md`, `PLANNING/06-api-react.md` →
 *     `<package>/planning/` (used by `get_api_reference`)
 *
 * Idempotent: removes any pre-existing destinations first, then mirrors each
 * source while skipping `node_modules`, VitePress build artefacts and dotfiles.
 */
import { copyFileSync, cpSync, existsSync, mkdirSync, rmSync, statSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const PACKAGE_ROOT = resolve(HERE, '..');
const DOCS_DEST = resolve(PACKAGE_ROOT, 'docs');
const PLANNING_DEST = resolve(PACKAGE_ROOT, 'planning');
const STUBS_DEST = resolve(PACKAGE_ROOT, 'stubs');
const UPSTREAM_STUB_NAME = 'resource.upstream.stub';

/** Files under `PLANNING/` that we ship. */
const PLANNING_FILES = ['03-adrs.md', '05-api-php.md', '06-api-react.md'];

function findRepoRoot(start) {
  let dir = start;
  for (let i = 0; i < 12; i += 1) {
    const candidate = resolve(dir, 'apps', 'docs');
    if (existsSync(candidate) && statSync(candidate).isDirectory()) return dir;
    const parent = dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }
  return null;
}

const repoRoot = findRepoRoot(PACKAGE_ROOT);
if (!repoRoot) {
  process.stderr.write(
    '[copy-resources] apps/docs not found. Skipping bundle (publishing without resources).\n',
  );
  process.exit(0);
}

/* ----- docs ------------------------------------------------------- */

const DOCS_SRC = resolve(repoRoot, 'apps', 'docs');
const SKIP_DIRS = new Set(['node_modules', '.vitepress', 'dist', 'public']);

function shouldCopyDoc(src) {
  const rel = relative(DOCS_SRC, src);
  if (rel === '') return true;
  const segments = rel.split(/[\\/]/);
  for (const seg of segments) {
    if (SKIP_DIRS.has(seg)) return false;
    if (seg.startsWith('.')) return false;
  }
  return true;
}

if (existsSync(DOCS_DEST)) rmSync(DOCS_DEST, { recursive: true, force: true });
cpSync(DOCS_SRC, DOCS_DEST, { recursive: true, filter: shouldCopyDoc });
process.stdout.write(`[copy-resources] Bundled ${DOCS_SRC} -> ${DOCS_DEST}\n`);

/* ----- planning --------------------------------------------------- */

if (existsSync(PLANNING_DEST)) rmSync(PLANNING_DEST, { recursive: true, force: true });
mkdirSync(PLANNING_DEST, { recursive: true });

let planningCount = 0;
for (const filename of PLANNING_FILES) {
  const src = resolve(repoRoot, 'PLANNING', filename);
  if (!existsSync(src)) {
    process.stderr.write(`[copy-resources] PLANNING/${filename} missing — skipped.\n`);
    continue;
  }
  const dest = resolve(PLANNING_DEST, filename);
  copyFileSync(src, dest);
  planningCount += 1;
}
process.stdout.write(
  `[copy-resources] Bundled ${planningCount} planning file(s) -> ${PLANNING_DEST}\n`,
);

/* ----- stubs ------------------------------------------------------ */

// `stubs/resource.stub` is checked-in (the JS-flavoured copy with `{{fields}}`
// token), so the package works in dev without prebuild having run. We
// additionally mirror the upstream PHP stub as `resource.upstream.stub` for
// drift detection — if the two diverge in non-trivial ways, maintainers see it
// in `git status` and can update the JS template accordingly.
mkdirSync(STUBS_DEST, { recursive: true });
const upstreamStubSrc = resolve(repoRoot, 'packages', 'core', 'stubs', 'resource.stub');
if (existsSync(upstreamStubSrc)) {
  copyFileSync(upstreamStubSrc, resolve(STUBS_DEST, UPSTREAM_STUB_NAME));
  process.stdout.write(
    `[copy-resources] Mirrored upstream stub -> ${resolve(STUBS_DEST, UPSTREAM_STUB_NAME)}\n`,
  );
} else {
  process.stderr.write('[copy-resources] upstream resource.stub missing — skipped.\n');
}
