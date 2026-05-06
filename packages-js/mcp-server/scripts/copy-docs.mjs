#!/usr/bin/env node
/**
 * Copies the monorepo docs corpus (`apps/docs`) into the package's `docs/`
 * folder so the published tarball ships with a self-contained docs index.
 *
 * Idempotent: removes any pre-existing destination first, then mirrors the
 * source while skipping `node_modules`, VitePress build artefacts and dotfiles.
 */
import { cpSync, existsSync, rmSync, statSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const PACKAGE_ROOT = resolve(HERE, '..');
const DEST = resolve(PACKAGE_ROOT, 'docs');

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
    '[copy-docs] apps/docs not found. Skipping bundle (publishing without docs).\n',
  );
  process.exit(0);
}

const SRC = resolve(repoRoot, 'apps', 'docs');

const SKIP_DIRS = new Set(['node_modules', '.vitepress', 'dist', 'public']);

function shouldCopy(src) {
  const rel = relative(SRC, src);
  if (rel === '') return true;
  const segments = rel.split(/[\\/]/);
  for (const seg of segments) {
    if (SKIP_DIRS.has(seg)) return false;
    if (seg.startsWith('.')) return false;
  }
  return true;
}

if (existsSync(DEST)) {
  rmSync(DEST, { recursive: true, force: true });
}

cpSync(SRC, DEST, {
  recursive: true,
  filter: (src) => shouldCopy(src),
});

process.stdout.write(`[copy-docs] Bundled ${SRC} -> ${DEST}\n`);
