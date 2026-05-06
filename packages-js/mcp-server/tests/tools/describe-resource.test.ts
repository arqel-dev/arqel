import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import type { ArtisanRunner } from '../../src/laravel/run-artisan.js';
import {
  createDescribeResourceTool,
  describeResource,
  describeResourceTool,
} from '../../src/tools/describe-resource.js';

let tempRoot: string;
let projectRoot: string;

const PAYLOAD = {
  version: '1.0.0',
  scope: 'resources',
  resources: [
    {
      class: 'App\\Resources\\PostResource',
      model: 'App\\Models\\Post',
      label: 'Post',
      pluralLabel: 'Posts',
      slug: 'posts',
      fields: [{ name: 'title', type: 'text' }],
      policies: ['view'],
    },
    {
      class: 'App\\Resources\\UserResource',
      model: null,
      label: null,
      pluralLabel: null,
      slug: null,
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
  tempRoot = mkdtempSync(join(tmpdir(), 'arqel-descres-'));
  projectRoot = join(tempRoot, 'project');
  mkdirSync(projectRoot);
  writeFileSync(join(projectRoot, 'artisan'), '');
});

afterEach(() => {
  rmSync(tempRoot, { recursive: true, force: true });
});

describe('describeResource()', () => {
  it('returns the matching resource by raw FQCN', async () => {
    const response = await describeResource(
      { class: 'App\\Resources\\PostResource', projectPath: projectRoot },
      { runner: okRunner },
    );
    expect('resource' in response).toBe(true);
    if (!('resource' in response)) return;
    expect(response.resource.slug).toBe('posts');
    expect(response.version).toBe('1.0.0');
  });

  it('normalizes a doubly-escaped FQCN before matching', async () => {
    const response = await describeResource(
      { class: 'App\\\\Resources\\\\PostResource', projectPath: projectRoot },
      { runner: okRunner },
    );
    expect('resource' in response).toBe(true);
  });

  it('normalizes a leading-backslash FQCN', async () => {
    const response = await describeResource(
      { class: '\\App\\Resources\\PostResource', projectPath: projectRoot },
      { runner: okRunner },
    );
    expect('resource' in response).toBe(true);
  });

  it('returns match=none with available list when class is unknown', async () => {
    const response = await describeResource(
      { class: 'App\\Resources\\NopeResource', projectPath: projectRoot },
      { runner: okRunner },
    );
    expect('match' in response).toBe(true);
    if (!('match' in response)) return;
    expect(response.match).toBe('none');
    expect(response.available).toContain('App\\Resources\\PostResource');
    expect(response.available).toContain('App\\Resources\\UserResource');
  });
});

describe('describe_resource tool', () => {
  it('exposes the canonical tool definition', () => {
    expect(describeResourceTool.definition.name).toBe('describe_resource');
    expect(describeResourceTool.definition.inputSchema.required).toContain('class');
  });

  it('rejects an empty class via the input schema', async () => {
    const result = await describeResourceTool.handle({ class: '' });
    expect(result.isError).toBe(true);
  });

  it('rejects missing class via the input schema', async () => {
    const result = await describeResourceTool.handle({});
    expect(result.isError).toBe(true);
  });

  it('happy path returns JSON envelope without isError', async () => {
    const tool = createDescribeResourceTool({ runner: okRunner });
    const result = await tool.handle({
      class: 'App\\Resources\\PostResource',
      projectPath: projectRoot,
    });
    expect(result.isError).toBeUndefined();
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      resource?: { slug?: string };
    };
    expect(parsed.resource?.slug).toBe('posts');
  });

  it('not-found path returns JSON envelope without isError (it is not an error)', async () => {
    const tool = createDescribeResourceTool({ runner: okRunner });
    const result = await tool.handle({
      class: 'App\\Resources\\Missing',
      projectPath: projectRoot,
    });
    expect(result.isError).toBeUndefined();
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as { match?: string };
    expect(parsed.match).toBe('none');
  });

  it('propagates ARTISAN_FAILED as isError envelope', async () => {
    const failing: ArtisanRunner = async () => ({
      stdout: '',
      stderr: 'oops',
      exitCode: 3,
    });
    const tool = createDescribeResourceTool({ runner: failing });
    const result = await tool.handle({
      class: 'App\\Whatever',
      projectPath: projectRoot,
    });
    expect(result.isError).toBe(true);
    const parsed = JSON.parse(result.content[0]?.text ?? '{}') as {
      error?: { code?: string };
    };
    expect(parsed.error?.code).toBe('ARTISAN_FAILED');
  });
});
