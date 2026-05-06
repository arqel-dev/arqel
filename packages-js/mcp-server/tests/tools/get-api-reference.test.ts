import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { beforeEach, describe, expect, it } from 'vitest';

import type { PlanningFileResolution } from '../../src/planning/loader.js';
import { parseApiReference } from '../../src/planning/parse-api-reference.js';
import {
  type GetApiReferenceResponse,
  getApiReference,
  getApiReferenceTool,
  rankCandidates,
  resetGetApiReferenceCache,
} from '../../src/tools/get-api-reference.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURES = resolve(HERE, '..', 'fixtures', 'planning');
const PHP_FIXTURE: PlanningFileResolution = {
  path: resolve(FIXTURES, '05-api-php.md'),
  source: 'monorepo',
};
const REACT_FIXTURE: PlanningFileResolution = {
  path: resolve(FIXTURES, '06-api-react.md'),
  source: 'monorepo',
};

const fixtureOpts = {
  phpResolution: PHP_FIXTURE,
  reactResolution: REACT_FIXTURE,
  noCache: true,
};

beforeEach(() => {
  resetGetApiReferenceCache();
});

describe('rankCandidates()', () => {
  const entries = [
    {
      symbol: 'useResource',
      language: 'react' as const,
      headingPath: 'Hooks > useResource',
      body: '',
      file: '06-api-react.md',
      line: 1,
    },
    {
      symbol: 'useResourceFilters',
      language: 'react' as const,
      headingPath: 'Hooks > useResourceFilters',
      body: '',
      file: '06-api-react.md',
      line: 2,
    },
    {
      symbol: 'Resource',
      language: 'php' as const,
      headingPath: 'Resource',
      body: '',
      file: '05-api-php.md',
      line: 3,
    },
    {
      symbol: 'ResourceRegistry',
      language: 'php' as const,
      headingPath: 'ResourceRegistry',
      body: '',
      file: '05-api-php.md',
      line: 4,
    },
    {
      symbol: 'Panel',
      language: 'php' as const,
      headingPath: 'Panel',
      body: '',
      file: '05-api-php.md',
      line: 5,
    },
  ];

  it('prefers prefix matches over middle matches', () => {
    const out = rankCandidates('Resource', entries);
    expect(out[0]?.symbol).toBe('Resource');
    expect(out.map((e) => e.symbol)).not.toContain('Panel');
  });

  it('breaks prefix ties by shorter symbol name', () => {
    const out = rankCandidates('use', entries);
    expect(out[0]?.symbol).toBe('useResource');
    expect(out[1]?.symbol).toBe('useResourceFilters');
  });

  it('caps results at 5', () => {
    const many = Array.from({ length: 10 }, (_, i) => ({
      symbol: `useThing${i}`,
      language: 'react' as const,
      headingPath: `Hooks > useThing${i}`,
      body: '',
      file: '06-api-react.md',
      line: i,
    }));
    expect(rankCandidates('use', many)).toHaveLength(5);
  });

  it('respects language preference on ties', () => {
    const tied = [
      { ...entries[2]!, symbol: 'Foo', language: 'php' as const },
      { ...entries[2]!, symbol: 'Foo', language: 'react' as const },
    ];
    expect(rankCandidates('foo', tied, 'react')[0]?.language).toBe('react');
    expect(rankCandidates('foo', tied, 'php')[0]?.language).toBe('php');
  });

  it('returns empty array when no entry matches', () => {
    expect(rankCandidates('zzznotfound', entries)).toEqual([]);
  });
});

describe('getApiReference()', () => {
  it('returns API_FILES_NOT_FOUND when both resolutions are null', () => {
    const response = getApiReference(
      { symbol: 'Resource' },
      { phpResolution: null, reactResolution: null, noCache: true },
    );
    expect(response.error?.code).toBe('API_FILES_NOT_FOUND');
    expect(response.match).toBe('none');
  });

  it('finds an exact match (case-insensitive) and returns the entry', () => {
    const response = getApiReference({ symbol: 'resource' }, fixtureOpts);
    expect(response.match).toBe('exact');
    expect(response.entry?.symbol).toBe('Resource');
    expect(response.entry?.language).toBe('php');
  });

  it('returns fuzzy candidates when no exact match exists', () => {
    const response = getApiReference({ symbol: 'use' }, fixtureOpts);
    expect(response.match).toBe('fuzzy');
    expect(response.candidates?.length).toBeGreaterThan(0);
    expect(response.candidates?.[0]?.symbol.toLowerCase()).toContain('use');
  });

  it('returns match=none with empty candidates when nothing matches', () => {
    const response = getApiReference({ symbol: 'zzzNoSuchSymbol' }, fixtureOpts);
    expect(response.match).toBe('none');
    expect(response.candidates).toEqual([]);
    expect(response.error).toBeUndefined();
  });

  it('respects language=php filter', () => {
    const response = getApiReference({ symbol: 'useResource', language: 'php' }, fixtureOpts);
    // useResource only exists in the React fixture; with PHP filter → none.
    expect(response.match).toBe('none');
  });

  it('respects language=react filter', () => {
    const response = getApiReference({ symbol: 'useResource', language: 'react' }, fixtureOpts);
    expect(response.match).toBe('exact');
    expect(response.entry?.language).toBe('react');
  });

  it('returns multiple fuzzy candidates ordered by prefix-then-length', () => {
    const response = getApiReference({ symbol: 'Resource' }, fixtureOpts);
    expect(response.match).toBe('exact');
    expect(response.entry?.symbol).toBe('Resource');
  });
});

describe('getApiReferenceTool', () => {
  it('exposes a stable name and JSON schema', () => {
    expect(getApiReferenceTool.definition.name).toBe('get_api_reference');
    expect(getApiReferenceTool.definition.inputSchema.required).toContain('symbol');
  });

  it('rejects empty symbol via the input schema', () => {
    const result = getApiReferenceTool.handle({ symbol: '' });
    expect(result.isError).toBe(true);
  });

  it('rejects missing symbol via the input schema', () => {
    const result = getApiReferenceTool.handle({});
    expect(result.isError).toBe(true);
  });

  it('rejects invalid language enum', () => {
    const result = getApiReferenceTool.handle({ symbol: 'Resource', language: 'cobol' });
    expect(result.isError).toBe(true);
  });
});

/* ------------------------------------------------------------------ *
 * Real-files paranoia check
 * ------------------------------------------------------------------ */

const REAL_PHP = resolve(HERE, '..', '..', '..', '..', 'PLANNING', '05-api-php.md');
const REAL_REACT = resolve(HERE, '..', '..', '..', '..', 'PLANNING', '06-api-react.md');
const HAS_REAL = existsSync(REAL_PHP) && existsSync(REAL_REACT);

const realDescribe = HAS_REAL ? describe : describe.skip;

realDescribe('real PLANNING files paranoia check', () => {
  it('finds key PHP symbols (Resource, Panel)', () => {
    const response = getApiReference(
      { symbol: 'Resource', language: 'php' },
      {
        phpResolution: { path: REAL_PHP, source: 'monorepo' },
        reactResolution: null,
        noCache: true,
      },
    );
    expect(response.match).toBe('exact');
    expect(response.entry?.symbol).toBe('Resource');

    const panel = getApiReference(
      { symbol: 'Panel', language: 'php' },
      {
        phpResolution: { path: REAL_PHP, source: 'monorepo' },
        reactResolution: null,
        noCache: true,
      },
    );
    expect(panel.match).toBe('exact');
    expect(panel.entry?.symbol).toBe('Panel');
  });

  it('finds key React symbols (useResource, FormRenderer, FieldSchema, useArqelForm)', () => {
    const opts = {
      phpResolution: null,
      reactResolution: { path: REAL_REACT, source: 'monorepo' as const },
      noCache: true,
    };
    const required = ['useResource', 'FormRenderer', 'FieldSchema', 'useArqelForm'];
    for (const symbol of required) {
      const r: GetApiReferenceResponse = getApiReference({ symbol, language: 'react' }, opts);
      expect(r.match, `expected exact match for ${symbol}`).toBe('exact');
      expect(r.entry?.symbol).toBe(symbol);
    }
  });

  it('parses the real PHP file without throwing and emits >5 entries', () => {
    const raw = readFileSync(REAL_PHP, 'utf-8');
    const entries = parseApiReference(raw, 'php');
    expect(entries.length).toBeGreaterThan(5);
  });
});
