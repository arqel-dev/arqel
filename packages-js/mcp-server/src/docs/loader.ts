import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export interface DocsResolution {
  /** Absolute path to the resolved docs directory. */
  docsDir: string;
  /** Absolute path to the repo root used for relative paths in results. */
  repoRoot: string;
  /** Where the docs were resolved from. */
  source: 'bundled' | 'monorepo';
}

export interface DocSection {
  /** Path relative to {@link DocsResolution.repoRoot}. */
  path: string;
  /** Heading text (without leading `#`s). Empty string for content before the first heading. */
  heading: string;
  /** Heading level: 1, 2, 3. 0 for the file preamble before any heading. */
  level: number;
  /** Section body (excluding the heading line itself). */
  body: string;
}

const EXCLUDED_DIRS = new Set(['node_modules', '.vitepress', '.git', 'dist', 'public']);

/**
 * Resolves the docs directory based on runtime context.
 *
 * Strategy:
 *   1. Bundled copy at `<package_root>/docs` (published tarball).
 *   2. Monorepo `apps/docs` reachable by walking up from the source file.
 */
export function resolveDocsDir(fromUrl: string = import.meta.url): DocsResolution | null {
  const here = fileURLToPath(fromUrl);
  const packageRoot = findPackageRoot(here);
  if (packageRoot) {
    const bundled = resolve(packageRoot, 'docs');
    if (isReadableDir(bundled)) {
      return { docsDir: bundled, repoRoot: packageRoot, source: 'bundled' };
    }
  }

  let dir = dirname(here);
  for (let i = 0; i < 12; i += 1) {
    const candidate = resolve(dir, 'apps', 'docs');
    if (isReadableDir(candidate)) {
      return { docsDir: candidate, repoRoot: dir, source: 'monorepo' };
    }
    const parent = dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }

  return null;
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

function isReadableDir(p: string): boolean {
  try {
    return statSync(p).isDirectory();
  } catch {
    return false;
  }
}

/**
 * Recursively collects every `.md` (and `.mdx`) file under `dir`.
 */
export function collectMarkdownFiles(dir: string): string[] {
  const out: string[] = [];
  walk(dir, out);
  return out;
}

function walk(dir: string, acc: string[]): void {
  let entries: string[];
  try {
    entries = readdirSync(dir);
  } catch {
    return;
  }
  for (const entry of entries) {
    if (EXCLUDED_DIRS.has(entry)) continue;
    if (entry.startsWith('.')) continue;
    const full = resolve(dir, entry);
    let info: ReturnType<typeof statSync>;
    try {
      info = statSync(full);
    } catch {
      continue;
    }
    if (info.isDirectory()) {
      walk(full, acc);
    } else if (info.isFile() && (full.endsWith('.md') || full.endsWith('.mdx'))) {
      acc.push(full);
    }
  }
}

/**
 * Splits a Markdown document into sections delimited by ATX headings.
 * Code fences are respected so a hash inside a fenced block does not split.
 */
export function splitSections(source: string, options: { path: string }): DocSection[] {
  const lines = source.split(/\r?\n/);
  const sections: DocSection[] = [];

  let inFence = false;
  let currentHeading = '';
  let currentLevel = 0;
  let currentBody: string[] = [];

  const flush = (): void => {
    const body = currentBody.join('\n').replace(/^\n+|\n+$/g, '');
    if (currentHeading === '' && body === '') return;
    sections.push({
      path: options.path,
      heading: currentHeading,
      level: currentLevel,
      body,
    });
  };

  for (const line of lines) {
    if (/^\s*```/.test(line)) {
      inFence = !inFence;
      currentBody.push(line);
      continue;
    }
    if (!inFence) {
      const headingMatch = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(line);
      if (headingMatch) {
        flush();
        currentHeading = headingMatch[2] ?? '';
        currentLevel = (headingMatch[1] ?? '').length;
        currentBody = [];
        continue;
      }
    }
    currentBody.push(line);
  }
  flush();

  return sections;
}

/**
 * Builds an index of {@link DocSection} entries for every Markdown file under `docsDir`.
 */
export function buildIndex(resolution: DocsResolution): DocSection[] {
  const files = collectMarkdownFiles(resolution.docsDir);
  const sections: DocSection[] = [];
  for (const file of files) {
    let raw: string;
    try {
      raw = readFileSync(file, 'utf-8');
    } catch {
      continue;
    }
    const relPath = relative(resolution.repoRoot, file).split('\\').join('/');
    sections.push(...splitSections(raw, { path: relPath }));
  }
  return sections;
}
