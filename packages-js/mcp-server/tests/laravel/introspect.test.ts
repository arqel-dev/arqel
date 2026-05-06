import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { introspectResources, normalizeFqcn } from '../../src/laravel/introspect.js';
import {
  type ArtisanRunner,
  ArtisanSpawnError,
  ArtisanTimeoutError,
} from '../../src/laravel/run-artisan.js';

let tempRoot: string;
let projectRoot: string;

const SAMPLE_PAYLOAD = {
  version: '0.8.1',
  scope: 'resources' as const,
  resources: [
    {
      class: 'App\\Resources\\PostResource',
      model: 'App\\Models\\Post',
      label: 'Post',
      pluralLabel: 'Posts',
      slug: 'posts',
      fields: [{ name: 'title', type: 'text' }],
      policies: ['view', 'create'],
    },
  ],
  panels: [],
  fields: [],
};

function fakeRunner(payload: unknown, exitCode = 0, stderr = ''): ArtisanRunner {
  return async () => ({
    stdout: typeof payload === 'string' ? payload : JSON.stringify(payload),
    stderr,
    exitCode,
  });
}

beforeEach(() => {
  tempRoot = mkdtempSync(join(tmpdir(), 'arqel-introspect-'));
  projectRoot = join(tempRoot, 'project');
  mkdirSync(projectRoot, { recursive: true });
  writeFileSync(join(projectRoot, 'artisan'), '#!/usr/bin/env php\n');
});

afterEach(() => {
  rmSync(tempRoot, { recursive: true, force: true });
});

describe('introspectResources()', () => {
  it('returns PROJECT_NOT_FOUND when no project can be resolved', async () => {
    const empty = mkdtempSync(join(tmpdir(), 'arqel-empty-'));
    try {
      const previous = process.env['ARQEL_PROJECT_PATH'];
      delete process.env['ARQEL_PROJECT_PATH'];
      const cwdSpy = vitestCwd(empty);
      try {
        const result = await introspectResources({ runner: fakeRunner(SAMPLE_PAYLOAD) });
        expect(result.ok).toBe(false);
        if (result.ok) return;
        expect(result.error.code).toBe('PROJECT_NOT_FOUND');
      } finally {
        cwdSpy.restore();
        if (previous !== undefined) process.env['ARQEL_PROJECT_PATH'] = previous;
      }
    } finally {
      rmSync(empty, { recursive: true, force: true });
    }
  });

  it('returns ok with parsed payload on the happy path', async () => {
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: fakeRunner(SAMPLE_PAYLOAD),
    });
    expect(result.ok).toBe(true);
    if (!result.ok) return;
    expect(result.project.root).toBe(projectRoot);
    expect(result.project.source).toBe('param');
    expect(result.data.version).toBe('0.8.1');
    expect(result.data.resources).toHaveLength(1);
  });

  it('returns ARTISAN_FAILED when artisan exits non-zero, with stderr truncated', async () => {
    const longStderr = 'x'.repeat(5000);
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: fakeRunner('', 1, longStderr),
    });
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.error.code).toBe('ARTISAN_FAILED');
    if (result.error.code !== 'ARTISAN_FAILED') return;
    expect(result.error.exitCode).toBe(1);
    expect(result.error.stderr.length).toBeLessThanOrEqual(2 * 1024 + 32);
    expect(result.error.stderr).toContain('truncated');
  });

  it('returns INVALID_JSON_OUTPUT when stdout is not JSON', async () => {
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: fakeRunner('not json at all', 0),
    });
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.error.code).toBe('INVALID_JSON_OUTPUT');
  });

  it('returns UNEXPECTED_OUTPUT when JSON does not match schema', async () => {
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: fakeRunner({ resources: 'not-an-array', scope: 'resources' }, 0),
    });
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.error.code).toBe('UNEXPECTED_OUTPUT');
    if (result.error.code !== 'UNEXPECTED_OUTPUT') return;
    expect(Array.isArray(result.error.issues)).toBe(true);
    expect(result.error.issues.length).toBeGreaterThan(0);
  });

  it('tolerates absence of optional panels/fields keys', async () => {
    const minimal = {
      version: null,
      scope: 'resources' as const,
      resources: [],
    };
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: fakeRunner(minimal),
    });
    expect(result.ok).toBe(true);
  });

  it('maps ArtisanTimeoutError thrown by the runner to ARTISAN_TIMEOUT', async () => {
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: async () => {
        throw new ArtisanTimeoutError(123);
      },
    });
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.error.code).toBe('ARTISAN_TIMEOUT');
  });

  it('maps ArtisanSpawnError thrown by the runner to ARTISAN_SPAWN_FAILED', async () => {
    const result = await introspectResources({
      projectPath: projectRoot,
      runner: async () => {
        throw new ArtisanSpawnError('no php');
      },
    });
    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.error.code).toBe('ARTISAN_SPAWN_FAILED');
  });
});

describe('normalizeFqcn()', () => {
  it('collapses runs of backslashes', () => {
    expect(normalizeFqcn('App\\\\Resources\\\\Post')).toBe('App\\Resources\\Post');
  });

  it('strips a leading backslash', () => {
    expect(normalizeFqcn('\\App\\Resources\\Post')).toBe('App\\Resources\\Post');
  });

  it('leaves already-normal FQCN untouched', () => {
    expect(normalizeFqcn('App\\Resources\\Post')).toBe('App\\Resources\\Post');
  });
});

/* ------------------------------------------------------------------ */
function vitestCwd(target: string): { restore: () => void } {
  const original = process.cwd;
  process.cwd = () => target;
  return {
    restore() {
      process.cwd = original;
    },
  };
}
