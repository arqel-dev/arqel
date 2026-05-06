import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { beforeEach, describe, expect, it } from 'vitest';

import type { PlanningFileResolution } from '../../src/planning/loader.js';
import { getAdr, getAdrTool, resetGetAdrCache } from '../../src/tools/get-adr.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const FIXTURE_PATH = resolve(HERE, '..', 'fixtures', 'planning', '03-adrs.md');
const fixtureResolution: PlanningFileResolution = {
  path: FIXTURE_PATH,
  source: 'monorepo',
};

beforeEach(() => {
  resetGetAdrCache();
});

describe('getAdr()', () => {
  it('returns ADRS_FILE_NOT_FOUND when resolution is null', () => {
    const response = getAdr({ id: '001' }, { resolution: null, noCache: true });
    expect(response.adr).toBeUndefined();
    expect(response.error?.code).toBe('ADRS_FILE_NOT_FOUND');
  });

  it('returns INVALID_ID for malformed input that survives the schema', () => {
    const response = getAdr({ id: 'abc' }, { resolution: fixtureResolution, noCache: true });
    expect(response.error?.code).toBe('INVALID_ID');
  });

  it('returns ADR_NOT_FOUND when normalized id has no entry', () => {
    const response = getAdr({ id: '999' }, { resolution: fixtureResolution, noCache: true });
    expect(response.error?.code).toBe('ADR_NOT_FOUND');
  });

  it('finds an ADR by short id', () => {
    const response = getAdr({ id: '1' }, { resolution: fixtureResolution, noCache: true });
    expect(response.error).toBeUndefined();
    expect(response.adr?.id).toBe('001');
    expect(response.adr?.title).toBe('First decision');
    expect(response.adr?.status).toBe('Accepted');
  });

  it('finds an ADR by ADR-NNN id (case-insensitive)', () => {
    const response = getAdr({ id: 'adr-002' }, { resolution: fixtureResolution, noCache: true });
    expect(response.adr?.id).toBe('002');
    expect(response.adr?.status).toBe('Proposed');
  });

  it('returns null status for ADRs without a Status/Estado line', () => {
    const response = getAdr({ id: '007' }, { resolution: fixtureResolution, noCache: true });
    expect(response.adr?.status).toBeNull();
  });
});

describe('getAdrTool', () => {
  it('exposes a stable name and JSON schema', () => {
    expect(getAdrTool.definition.name).toBe('get_adr');
    expect(getAdrTool.definition.inputSchema.required).toContain('id');
  });

  it('rejects empty id via the input schema', () => {
    const result = getAdrTool.handle({ id: '' });
    expect(result.isError).toBe(true);
  });

  it('rejects missing id via the input schema', () => {
    const result = getAdrTool.handle({});
    expect(result.isError).toBe(true);
  });

  it('returns isError + JSON envelope for INVALID_ID', () => {
    const result = getAdrTool.handle({ id: 'not-an-id' });
    expect(result.isError).toBe(true);
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      error?: { code?: string };
    };
    expect(parsed.error?.code).toBe('INVALID_ID');
  });
});
