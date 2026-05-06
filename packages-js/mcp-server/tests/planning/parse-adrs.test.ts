import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { resolvePlanningFile } from '../../src/planning/loader.js';
import { normalizeAdrId, parseAdrs } from '../../src/planning/parse-adrs.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURE = resolve(HERE, '..', 'fixtures', 'planning', '03-adrs.md');

describe('normalizeAdrId()', () => {
  it.each([
    ['001', '001'],
    ['1', '001'],
    ['ADR-001', '001'],
    ['adr-1', '001'],
    ['ADR001', '001'],
    [' adr-016 ', '016'],
    ['16', '016'],
    ['018', '018'],
    ['19', '019'],
    ['9999', '9999'],
  ])('normalises %p -> %p', (input, expected) => {
    expect(normalizeAdrId(input)).toBe(expected);
  });

  it.each([
    '',
    '   ',
    'abc',
    'ADR-',
    'ADR-abc',
    '1a',
    '#001',
    'adr/1',
  ])('rejects invalid input %p', (input) => {
    expect(normalizeAdrId(input)).toBeNull();
  });

  it('rejects non-string input defensively', () => {
    // @ts-expect-error - testing runtime guard
    expect(normalizeAdrId(undefined)).toBeNull();
    // @ts-expect-error - testing runtime guard
    expect(normalizeAdrId(123)).toBeNull();
  });
});

describe('parseAdrs()', () => {
  const source = readFileSync(FIXTURE, 'utf-8');
  const adrs = parseAdrs(source);

  it('finds every ADR heading', () => {
    expect(Array.from(adrs.keys()).sort()).toEqual(['001', '002', '007']);
  });

  it('extracts title, status (Status:) and body', () => {
    const adr = adrs.get('001');
    expect(adr?.title).toBe('First decision');
    expect(adr?.status).toBe('Accepted');
    expect(adr?.body).toContain('### Contexto');
    expect(adr?.body).toContain('Pick option A.');
  });

  it('extracts PT-BR Estado: label as status', () => {
    expect(adrs.get('002')?.status).toBe('Proposed');
  });

  it('returns null status when no Status/Estado line is present', () => {
    expect(adrs.get('007')?.status).toBeNull();
  });

  it('preserves nested headings inside the body', () => {
    expect(adrs.get('002')?.body).toContain('### subheading');
  });

  it('does not treat ADR-style strings inside fenced code blocks as new ADRs', () => {
    expect(adrs.has('999')).toBe(false);
    expect(adrs.get('001')?.body).toContain('## ADR-999');
  });
});

describe('paranoia: real PLANNING/03-adrs.md', () => {
  const resolution = resolvePlanningFile('03-adrs.md', { noCache: true });
  const test = resolution ? it : it.skip;

  test('parses all 18 canonical ADRs (001..018)', () => {
    if (!resolution) return; // satisfies type narrowing for the skipped path
    const raw = readFileSync(resolution.path, 'utf-8');
    const adrs = parseAdrs(raw);
    const ids = Array.from(adrs.keys()).sort();
    const expected = Array.from({ length: 18 }, (_, i) => String(i + 1).padStart(3, '0'));
    expect(ids).toEqual(expected);
  });
});
