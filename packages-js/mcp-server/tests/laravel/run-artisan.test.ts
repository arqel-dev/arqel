import { chmodSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

import {
  ArtisanSpawnError,
  ArtisanTimeoutError,
  runArtisan,
} from '../../src/laravel/run-artisan.js';

/**
 * `runArtisan` always invokes `<bin> artisan <...args>` from `root`. To
 * keep the tests hermetic we point `bin` at `process.execPath` (node)
 * and write a real `artisan` JS file in the temp project root that
 * implements the per-test behaviour. Node will execute that file as a
 * CommonJS script.
 */

function makeProject(scriptBody: string): string {
  const dir = mkdtempSync(join(tmpdir(), 'arqel-runartisan-'));
  const artisan = join(dir, 'artisan');
  writeFileSync(artisan, scriptBody);
  chmodSync(artisan, 0o755);
  return dir;
}

describe('runArtisan()', () => {
  it('captures stdout, stderr and exitCode on success', async () => {
    const dir = makeProject(
      `process.stdout.write('payload');process.stderr.write('warn');process.exit(0);`,
    );
    try {
      const result = await runArtisan(dir, [], { bin: process.execPath });
      expect(result.exitCode).toBe(0);
      expect(result.stdout).toBe('payload');
      expect(result.stderr).toBe('warn');
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });

  it('returns the non-zero exit code without rejecting', async () => {
    const dir = makeProject(`process.stderr.write('boom');process.exit(7);`);
    try {
      const result = await runArtisan(dir, ['ignored'], { bin: process.execPath });
      expect(result.exitCode).toBe(7);
      expect(result.stderr).toContain('boom');
      expect(result.stdout).toBe('');
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });

  it('rejects with ArtisanTimeoutError when the process hangs past the timeout', async () => {
    const dir = makeProject(`setInterval(() => {}, 1000);`);
    try {
      await expect(
        runArtisan(dir, [], { bin: process.execPath, timeoutMs: 200 }),
      ).rejects.toBeInstanceOf(ArtisanTimeoutError);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });

  it('rejects with ArtisanSpawnError when the binary cannot be found', async () => {
    const dir = makeProject(`process.exit(0);`);
    try {
      await expect(
        runArtisan(dir, [], { bin: '/definitely/not/a/real/binary-xyz-arqel' }),
      ).rejects.toBeInstanceOf(ArtisanSpawnError);
    } finally {
      rmSync(dir, { recursive: true, force: true });
    }
  });
});
