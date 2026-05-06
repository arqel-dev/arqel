import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import {
  buildIndex,
  collectMarkdownFiles,
  resolveDocsDir,
  splitSections,
} from '../../src/docs/loader.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURES = resolve(HERE, '..', 'fixtures', 'docs');

describe('splitSections', () => {
  it('splits a markdown file into heading-bounded sections', () => {
    const md = '# Title\n\nIntro paragraph.\n\n## Sub\n\nbody line\n';
    const sections = splitSections(md, { path: 'a.md' });
    expect(sections).toHaveLength(2);
    expect(sections[0]?.heading).toBe('Title');
    expect(sections[0]?.level).toBe(1);
    expect(sections[0]?.body).toContain('Intro paragraph.');
    expect(sections[1]?.heading).toBe('Sub');
    expect(sections[1]?.level).toBe(2);
    expect(sections[1]?.body).toContain('body line');
  });

  it('does not treat hashes inside code fences as headings', () => {
    const md = '# Real\n\n```bash\n# fake heading\n```\n\nstill in real.\n';
    const sections = splitSections(md, { path: 'b.md' });
    expect(sections).toHaveLength(1);
    expect(sections[0]?.heading).toBe('Real');
    expect(sections[0]?.body).toContain('# fake heading');
  });

  it('captures preamble before the first heading as a level-0 section', () => {
    const md = 'preamble line\n\n# Heading\nbody\n';
    const sections = splitSections(md, { path: 'c.md' });
    expect(sections).toHaveLength(2);
    expect(sections[0]?.heading).toBe('');
    expect(sections[0]?.level).toBe(0);
    expect(sections[0]?.body).toContain('preamble line');
  });
});

describe('collectMarkdownFiles', () => {
  it('returns every .md file under the fixtures dir', () => {
    const files = collectMarkdownFiles(FIXTURES);
    expect(files.length).toBe(3);
    expect(files.every((f: string) => f.endsWith('.md'))).toBe(true);
  });
});

describe('buildIndex', () => {
  it('produces sections for every fixture file', () => {
    const sections = buildIndex({
      docsDir: FIXTURES,
      repoRoot: resolve(FIXTURES, '..'),
      source: 'monorepo',
    });
    expect(sections.length).toBeGreaterThan(3);
    const paths = new Set<string>(sections.map((s) => s.path));
    expect([...paths].some((p) => p.endsWith('docs/guide/getting-started.md'))).toBe(true);
  });
});

describe('resolveDocsDir', () => {
  it('finds either bundled or monorepo docs from this package', () => {
    const resolution = resolveDocsDir();
    expect(resolution).not.toBeNull();
    expect(['bundled', 'monorepo']).toContain(resolution?.source);
  });
});
