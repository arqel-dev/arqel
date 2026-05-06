import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { beforeEach, describe, expect, it } from 'vitest';
import type { DocsResolution } from '../../src/docs/loader.js';
import { resetSearchDocsCache, searchDocs, searchDocsTool } from '../../src/tools/search-docs.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURES = resolve(HERE, '..', 'fixtures', 'docs');
const fixtureResolution: DocsResolution = {
  docsDir: FIXTURES,
  repoRoot: resolve(FIXTURES, '..'),
  source: 'monorepo',
};

beforeEach(() => {
  resetSearchDocsCache();
});

describe('searchDocs()', () => {
  it('returns DOCS_NOT_FOUND when resolution is null', () => {
    const response = searchDocs({ query: 'anything' }, { resolution: null, noCache: true });
    expect(response.results).toEqual([]);
    expect(response.error?.code).toBe('DOCS_NOT_FOUND');
  });

  it('returns no matches for a query absent from the corpus', () => {
    const response = searchDocs(
      { query: 'thisterm-should-not-exist-zzzz' },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(response.results).toEqual([]);
    expect(response.error).toBeUndefined();
  });

  it('matches in heading and body', () => {
    const response = searchDocs(
      { query: 'panel' },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(response.results.length).toBeGreaterThan(0);
    const headings = response.results.map((r) => r.heading);
    // "Configuration" body mentions panel; "Trailing" mentions panel.
    expect(headings.some((h) => h === 'Configuration' || h === 'Trailing')).toBe(true);
  });

  it('scores heading hits higher than body hits', () => {
    const response = searchDocs(
      { query: 'fields' },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(response.results.length).toBeGreaterThan(0);
    // The "Fields Reference" heading has the term in the heading -> higher score.
    const top = response.results[0];
    expect(top?.heading.toLowerCase()).toContain('fields');
    // Sorted descending.
    for (let i = 1; i < response.results.length; i += 1) {
      const prev = response.results[i - 1];
      const cur = response.results[i];
      expect(prev && cur && prev.score >= cur.score).toBe(true);
    }
  });

  it('clamps limit to MAX_LIMIT and respects custom limit', () => {
    const oneOnly = searchDocs(
      { query: 'a', limit: 1 },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(oneOnly.results.length).toBeLessThanOrEqual(1);
    const huge = searchDocs(
      { query: 'a', limit: 9999 },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(huge.results.length).toBeLessThanOrEqual(25);
  });

  it('produces a path relative to repoRoot and an excerpt with **bold** match', () => {
    const response = searchDocs(
      { query: 'Recharts' },
      { resolution: fixtureResolution, noCache: true },
    );
    expect(response.results).toHaveLength(1);
    const hit = response.results[0];
    expect(hit?.path.endsWith('docs/advanced/widgets.md')).toBe(true);
    expect(hit?.excerpt).toContain('**Recharts**');
  });

  it('returns case-insensitive matches', () => {
    const lower = searchDocs({ query: 'arqel' }, { resolution: fixtureResolution, noCache: true });
    const upper = searchDocs({ query: 'ARQEL' }, { resolution: fixtureResolution, noCache: true });
    expect(lower.results.length).toBe(upper.results.length);
    expect(lower.results.length).toBeGreaterThan(0);
  });

  it('rejects empty queries via the input schema', () => {
    const result = searchDocsTool.handle({ query: '' });
    expect(result.isError).toBe(true);
  });

  it('returns text content blocks via the tool handle', () => {
    const result = searchDocsTool.handle({ query: 'arqel' });
    expect(result.isError).toBeUndefined();
    expect(result.content[0]?.type).toBe('text');
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      results?: unknown;
    };
    expect(Array.isArray(parsed.results)).toBe(true);
  });
});

describe('searchDocsTool.definition', () => {
  it('exposes a stable name and JSON schema', () => {
    expect(searchDocsTool.definition.name).toBe('search_docs');
    expect(searchDocsTool.definition.inputSchema.required).toContain('query');
  });
});
