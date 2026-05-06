import { existsSync, statSync } from 'node:fs';
import { dirname, parse, resolve } from 'node:path';

export type ProjectSource = 'param' | 'env' | 'cwd';

export interface ResolvedProject {
  root: string;
  source: ProjectSource;
}

export interface ResolveLaravelProjectOptions {
  /** Explicit project path (highest priority). */
  projectPath?: string | undefined;
  /** Override starting cwd for the walk-up step. Defaults to `process.cwd()`. */
  cwd?: string | undefined;
  /** Override env reader. Defaults to `process.env.ARQEL_PROJECT_PATH`. */
  envProjectPath?: string | undefined;
}

function isLaravelRoot(dir: string): boolean {
  try {
    const artisan = resolve(dir, 'artisan');
    if (!existsSync(artisan)) return false;
    return statSync(artisan).isFile();
  } catch {
    return false;
  }
}

function walkUp(start: string): string | null {
  let current = resolve(start);
  const fsRoot = parse(current).root;
  // Defensive cap to avoid pathological loops.
  for (let i = 0; i < 64; i += 1) {
    if (isLaravelRoot(current)) return current;
    if (current === fsRoot) return null;
    const parent = dirname(current);
    if (parent === current) return null;
    current = parent;
  }
  return null;
}

/**
 * Resolve the Laravel project root using:
 *
 *   1. `projectPath` parameter (if non-empty).
 *   2. `ARQEL_PROJECT_PATH` env var.
 *   3. Walk up from `cwd` (default `process.cwd()`) looking for an `artisan` file.
 *
 * Returns `null` when no candidate is found.
 *
 * Note: when (1) or (2) provide a path, it is used **as-is** even if it does
 * not contain an `artisan` file. The caller is trusted to know its project.
 */
export async function resolveLaravelProject(
  options: ResolveLaravelProjectOptions = {},
): Promise<ResolvedProject | null> {
  const { projectPath } = options;
  if (typeof projectPath === 'string' && projectPath.trim() !== '') {
    return { root: resolve(projectPath), source: 'param' };
  }

  const envValue =
    'envProjectPath' in options ? options.envProjectPath : process.env['ARQEL_PROJECT_PATH'];
  if (typeof envValue === 'string' && envValue.trim() !== '') {
    return { root: resolve(envValue), source: 'env' };
  }

  const start = options.cwd ?? process.cwd();
  const found = walkUp(start);
  if (found) return { root: found, source: 'cwd' };

  return null;
}
