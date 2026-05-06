import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { resolveLaravelProject } from '../../src/laravel/resolve-project.js';

let tempRoot: string;

beforeEach(() => {
  tempRoot = mkdtempSync(join(tmpdir(), 'arqel-resolve-'));
});

afterEach(() => {
  rmSync(tempRoot, { recursive: true, force: true });
});

function writeArtisan(dir: string): void {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'artisan'), '#!/usr/bin/env php\n');
}

describe('resolveLaravelProject()', () => {
  it('returns null when nothing matches and no env/param given', async () => {
    const nested = join(tempRoot, 'a', 'b', 'c');
    mkdirSync(nested, { recursive: true });
    const result = await resolveLaravelProject({ cwd: nested, envProjectPath: undefined });
    expect(result).toBeNull();
  });

  it('walks up from cwd until it finds an artisan file', async () => {
    const projectRoot = join(tempRoot, 'project');
    writeArtisan(projectRoot);
    const nested = join(projectRoot, 'app', 'Resources');
    mkdirSync(nested, { recursive: true });

    const result = await resolveLaravelProject({ cwd: nested, envProjectPath: undefined });
    expect(result?.source).toBe('cwd');
    expect(result?.root).toBe(projectRoot);
  });

  it('uses ARQEL_PROJECT_PATH env when no param is given', async () => {
    const envProject = join(tempRoot, 'env-project');
    writeArtisan(envProject);
    const elsewhere = join(tempRoot, 'elsewhere');
    mkdirSync(elsewhere, { recursive: true });

    const result = await resolveLaravelProject({
      cwd: elsewhere,
      envProjectPath: envProject,
    });
    expect(result?.source).toBe('env');
    expect(result?.root).toBe(envProject);
  });

  it('prefers the explicit projectPath param over env and cwd', async () => {
    const paramProject = join(tempRoot, 'param-project');
    writeArtisan(paramProject);
    const envProject = join(tempRoot, 'env-project');
    writeArtisan(envProject);
    const cwdProject = join(tempRoot, 'cwd-project');
    writeArtisan(cwdProject);

    const result = await resolveLaravelProject({
      projectPath: paramProject,
      cwd: cwdProject,
      envProjectPath: envProject,
    });
    expect(result?.source).toBe('param');
    expect(result?.root).toBe(paramProject);
  });

  it('does not validate the projectPath param — it is taken on trust', async () => {
    const fake = join(tempRoot, 'does-not-exist');
    const result = await resolveLaravelProject({
      projectPath: fake,
      envProjectPath: undefined,
    });
    expect(result?.source).toBe('param');
    expect(result?.root).toBe(fake);
  });

  it('ignores empty/whitespace param and env and falls through to cwd', async () => {
    const projectRoot = join(tempRoot, 'project');
    writeArtisan(projectRoot);

    const result = await resolveLaravelProject({
      projectPath: '   ',
      envProjectPath: '',
      cwd: projectRoot,
    });
    expect(result?.source).toBe('cwd');
    expect(result?.root).toBe(projectRoot);
  });

  it('reads ARQEL_PROJECT_PATH lazily from process.env when envProjectPath option is omitted', async () => {
    const envProject = join(tempRoot, 'lazy-env');
    writeArtisan(envProject);
    const previous = process.env['ARQEL_PROJECT_PATH'];
    process.env['ARQEL_PROJECT_PATH'] = envProject;
    try {
      const elsewhere = join(tempRoot, 'elsewhere2');
      mkdirSync(elsewhere, { recursive: true });
      const result = await resolveLaravelProject({ cwd: elsewhere });
      expect(result?.source).toBe('env');
      expect(result?.root).toBe(envProject);
    } finally {
      if (previous === undefined) delete process.env['ARQEL_PROJECT_PATH'];
      else process.env['ARQEL_PROJECT_PATH'] = previous;
    }
  });
});
