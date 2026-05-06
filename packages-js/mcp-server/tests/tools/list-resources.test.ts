import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import type { ArtisanRunner } from '../../src/laravel/run-artisan.js';
import {
  createListResourcesTool,
  listResources,
  listResourcesTool,
} from '../../src/tools/list-resources.js';

let tempRoot: string;
let projectRoot: string;

const PAYLOAD = {
  version: '1.2.3',
  scope: 'resources',
  resources: [
    {
      class: 'App\\Resources\\PostResource',
      model: 'App\\Models\\Post',
      label: 'Post',
      pluralLabel: 'Posts',
      slug: 'posts',
      fields: [],
      policies: [],
    },
  ],
  panels: [],
  fields: [],
};

const okRunner: ArtisanRunner = async () => ({
  stdout: JSON.stringify(PAYLOAD),
  stderr: '',
  exitCode: 0,
});

beforeEach(() => {
  tempRoot = mkdtempSync(join(tmpdir(), 'arqel-listres-'));
  projectRoot = join(tempRoot, 'project');
  mkdirSync(projectRoot);
  writeFileSync(join(projectRoot, 'artisan'), '');
});

afterEach(() => {
  rmSync(tempRoot, { recursive: true, force: true });
});

describe('listResources()', () => {
  it('returns version + resources on the happy path', async () => {
    const response = await listResources({ projectPath: projectRoot }, { runner: okRunner });
    expect('error' in response).toBe(false);
    if ('error' in response) return;
    expect(response.version).toBe('1.2.3');
    expect(response.resources).toHaveLength(1);
    expect(response.project.source).toBe('param');
  });

  it('returns the error envelope when the project cannot be resolved', async () => {
    const empty = mkdtempSync(join(tmpdir(), 'arqel-listres-empty-'));
    const previous = process.env['ARQEL_PROJECT_PATH'];
    delete process.env['ARQEL_PROJECT_PATH'];
    const originalCwd = process.cwd;
    process.cwd = () => empty;
    try {
      const response = await listResources({}, { runner: okRunner });
      expect('error' in response).toBe(true);
      if (!('error' in response)) return;
      expect(response.error.code).toBe('PROJECT_NOT_FOUND');
    } finally {
      process.cwd = originalCwd;
      if (previous !== undefined) process.env['ARQEL_PROJECT_PATH'] = previous;
      rmSync(empty, { recursive: true, force: true });
    }
  });
});

describe('list_resources tool', () => {
  it('exposes the canonical tool definition', () => {
    expect(listResourcesTool.definition.name).toBe('list_resources');
    expect(listResourcesTool.definition.inputSchema.required).toEqual([]);
  });

  it('returns isError=true when the inner pipeline fails', async () => {
    const failingRunner: ArtisanRunner = async () => ({
      stdout: '',
      stderr: 'syntax error',
      exitCode: 2,
    });
    const tool = createListResourcesTool({ runner: failingRunner });
    const result = await tool.handle({ projectPath: projectRoot });
    expect(result.isError).toBe(true);
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      error?: { code?: string };
    };
    expect(parsed.error?.code).toBe('ARTISAN_FAILED');
  });

  it('happy path returns the JSON envelope without isError', async () => {
    const tool = createListResourcesTool({ runner: okRunner });
    const result = await tool.handle({ projectPath: projectRoot });
    expect(result.isError).toBeUndefined();
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      version?: string;
      resources?: unknown[];
    };
    expect(parsed.version).toBe('1.2.3');
    expect(parsed.resources).toHaveLength(1);
  });

  it('rejects an empty projectPath via the input schema', async () => {
    const result = await listResourcesTool.handle({ projectPath: '' });
    expect(result.isError).toBe(true);
  });
});
