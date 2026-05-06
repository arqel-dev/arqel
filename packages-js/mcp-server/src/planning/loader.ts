import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export interface PlanningFileResolution {
  /** Absolute path to the resolved planning file. */
  path: string;
  /** Where the file was resolved from. */
  source: 'bundled' | 'monorepo';
}

interface CacheKey {
  filename: string;
  fromUrl: string;
}

const cache = new Map<string, PlanningFileResolution | null>();

function cacheKeyOf(key: CacheKey): string {
  return `${key.fromUrl}::${key.filename}`;
}

/**
 * Resolves a `PLANNING/<filename>` file based on runtime context.
 *
 * Strategy:
 *   1. Bundled copy at `<package_root>/planning/<filename>` (published tarball).
 *   2. Monorepo `PLANNING/<filename>` reachable by walking up from the source file.
 *
 * Decision is cached per-process keyed by `(filename, fromUrl)` so repeated
 * lookups are cheap. Pass `noCache` for tests.
 */
export function resolvePlanningFile(
  filename: string,
  options: { fromUrl?: string; noCache?: boolean } = {},
): PlanningFileResolution | null {
  const fromUrl = options.fromUrl ?? import.meta.url;
  const key = cacheKeyOf({ filename, fromUrl });
  if (!options.noCache && cache.has(key)) {
    return cache.get(key) ?? null;
  }

  const here = fileURLToPath(fromUrl);
  const packageRoot = findPackageRoot(here);
  if (packageRoot) {
    const bundled = resolve(packageRoot, 'planning', filename);
    if (isReadableFile(bundled)) {
      const resolution: PlanningFileResolution = { path: bundled, source: 'bundled' };
      if (!options.noCache) cache.set(key, resolution);
      return resolution;
    }
  }

  let dir = dirname(here);
  for (let i = 0; i < 12; i += 1) {
    const candidate = resolve(dir, 'PLANNING', filename);
    if (isReadableFile(candidate)) {
      const resolution: PlanningFileResolution = { path: candidate, source: 'monorepo' };
      if (!options.noCache) cache.set(key, resolution);
      return resolution;
    }
    const parent = dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }

  if (!options.noCache) cache.set(key, null);
  return null;
}

/** Reset the resolver cache. Exposed for tests. */
export function resetPlanningLoaderCache(): void {
  cache.clear();
}

function findPackageRoot(startFile: string): string | null {
  let dir = dirname(startFile);
  for (let i = 0; i < 12; i += 1) {
    const pkgPath = resolve(dir, 'package.json');
    if (existsSync(pkgPath)) {
      try {
        const pkg = JSON.parse(readFileSync(pkgPath, 'utf-8')) as { name?: string };
        if (pkg.name === '@arqel-dev/mcp-server') return dir;
      } catch {
        // ignore malformed package.json
      }
    }
    const parent = dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }
  return null;
}

function isReadableFile(p: string): boolean {
  try {
    return statSync(p).isFile();
  } catch {
    return false;
  }
}
