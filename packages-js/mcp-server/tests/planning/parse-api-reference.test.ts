import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import {
  isGenericSubheading,
  parseApiReference,
  stripHeadingNumbering,
} from '../../src/planning/parse-api-reference.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURES = resolve(HERE, '..', 'fixtures', 'planning');
const PHP_FIXTURE = readFileSync(resolve(FIXTURES, '05-api-php.md'), 'utf-8');
const REACT_FIXTURE = readFileSync(resolve(FIXTURES, '06-api-react.md'), 'utf-8');

describe('stripHeadingNumbering()', () => {
  it('strips `N.` prefixes', () => {
    expect(stripHeadingNumbering('1. Resource')).toBe('Resource');
  });

  it('strips `N.M` prefixes', () => {
    expect(stripHeadingNumbering('9.1 useResource')).toBe('useResource');
  });

  it('returns text unchanged when there is no numbering', () => {
    expect(stripHeadingNumbering('Plain title')).toBe('Plain title');
  });
});

describe('isGenericSubheading()', () => {
  it('flags known generic phrases', () => {
    expect(isGenericSubheading('1.1 Base class')).toBe(true);
    expect(isGenericSubheading('Examples')).toBe(true);
    expect(isGenericSubheading('Config')).toBe(true);
  });

  it('flags all-lowercase descriptors as generic', () => {
    expect(isGenericSubheading('config')).toBe(true);
    expect(isGenericSubheading('automatic')).toBe(true);
  });

  it('keeps PascalCase identifiers concrete', () => {
    expect(isGenericSubheading('Panel')).toBe(false);
    expect(isGenericSubheading('FieldSchema')).toBe(false);
  });

  it('keeps camelCase identifiers concrete (lowercase first, uppercase later)', () => {
    expect(isGenericSubheading('useResource')).toBe(false);
    expect(isGenericSubheading('9.1 useResource')).toBe(false);
    expect(isGenericSubheading('useArqelForm')).toBe(false);
  });

  it('flags capitalised generic phrases via the known list', () => {
    expect(isGenericSubheading('1.1 Base API')).toBe(true);
  });
});

describe('parseApiReference() — PHP fixture', () => {
  const entries = parseApiReference(PHP_FIXTURE, 'php');

  it('emits the parent symbol for `## 1. Resource`', () => {
    const resource = entries.find((e) => e.symbol === 'Resource');
    expect(resource).toBeDefined();
    expect(resource?.language).toBe('php');
    expect(resource?.file).toBe('05-api-php.md');
    expect(resource?.headingPath).toBe('Resource');
    // Parent body must include the (folded) generic child content.
    expect(resource?.body).toContain('UserResource extends Resource');
  });

  it('skips generic `### 1.1 Base class` as its own entry', () => {
    expect(entries.find((e) => e.symbol === 'Base class')).toBeUndefined();
  });

  it('emits concrete `### 1.2 Resource discovery` as its own entry', () => {
    const discovery = entries.find((e) => e.symbol === 'Resource discovery');
    expect(discovery).toBeDefined();
    expect(discovery?.headingPath).toBe('Resource > Resource discovery');
    expect(discovery?.body).toContain('Panel::resources()');
  });

  it('emits a `## Panel` parent entry with no children', () => {
    const panel = entries.find((e) => e.symbol === 'Panel');
    expect(panel).toBeDefined();
    expect(panel?.headingPath).toBe('Panel');
  });
});

describe('parseApiReference() — React fixture', () => {
  const entries = parseApiReference(REACT_FIXTURE, 'react');

  it('emits FieldSchema parent and folds Examples into it', () => {
    const fs = entries.find((e) => e.symbol === 'FieldSchema');
    expect(fs).toBeDefined();
    expect(fs?.body).toContain("type: 'text'");
    expect(entries.find((e) => e.symbol === 'Examples')).toBeUndefined();
  });

  it('emits camelCase hooks as their own concrete entries', () => {
    expect(entries.find((e) => e.symbol === 'useResource')).toBeDefined();
    expect(entries.find((e) => e.symbol === 'useArqelForm')).toBeDefined();
  });

  it('skips lowercase-only `### 2.3 config`', () => {
    expect(entries.find((e) => e.symbol === 'config')).toBeUndefined();
  });

  it('tags entries with language=react and the right filename', () => {
    for (const e of entries) {
      expect(e.language).toBe('react');
      expect(e.file).toBe('06-api-react.md');
    }
  });
});
