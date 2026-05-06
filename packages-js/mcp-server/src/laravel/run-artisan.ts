import { spawn } from 'node:child_process';

export interface RunArtisanResult {
  stdout: string;
  stderr: string;
  exitCode: number;
}

export interface RunArtisanOptions {
  /** Timeout in milliseconds. Defaults to ARQEL_ARTISAN_TIMEOUT_MS env var or 15000. */
  timeoutMs?: number;
  /** Override the binary used to run artisan. Defaults to ARQEL_PHP_BIN env var or "php". */
  bin?: string;
}

export class ArtisanTimeoutError extends Error {
  public readonly code = 'ARTISAN_TIMEOUT' as const;
  public readonly timeoutMs: number;
  constructor(timeoutMs: number) {
    super(`artisan command timed out after ${timeoutMs}ms`);
    this.name = 'ArtisanTimeoutError';
    this.timeoutMs = timeoutMs;
  }
}

export class ArtisanSpawnError extends Error {
  public readonly code = 'ARTISAN_SPAWN_FAILED' as const;
  public override readonly cause: unknown;
  constructor(message: string, cause?: unknown) {
    super(message);
    this.name = 'ArtisanSpawnError';
    this.cause = cause;
  }
}

const DEFAULT_TIMEOUT_MS = 15_000;

function resolveTimeout(opt: RunArtisanOptions): number {
  if (typeof opt.timeoutMs === 'number' && Number.isFinite(opt.timeoutMs) && opt.timeoutMs > 0) {
    return opt.timeoutMs;
  }
  const fromEnv = process.env['ARQEL_ARTISAN_TIMEOUT_MS'];
  if (typeof fromEnv === 'string') {
    const parsed = Number.parseInt(fromEnv, 10);
    if (Number.isFinite(parsed) && parsed > 0) return parsed;
  }
  return DEFAULT_TIMEOUT_MS;
}

function resolveBin(opt: RunArtisanOptions): string {
  if (typeof opt.bin === 'string' && opt.bin.trim() !== '') return opt.bin;
  const envBin = process.env['ARQEL_PHP_BIN'];
  if (typeof envBin === 'string' && envBin.trim() !== '') return envBin;
  return 'php';
}

/**
 * Run a Laravel artisan command in the given project root.
 *
 * Resolves with `{ stdout, stderr, exitCode }` regardless of the exit code
 * (callers inspect the result). Rejects only when:
 *   - the binary cannot be spawned (`ArtisanSpawnError`);
 *   - the command exceeds the timeout (`ArtisanTimeoutError`).
 *
 * Args are passed as an array — no shell interpretation.
 */
export function runArtisan(
  root: string,
  args: string[],
  options: RunArtisanOptions = {},
): Promise<RunArtisanResult> {
  const timeoutMs = resolveTimeout(options);
  const bin = resolveBin(options);

  return new Promise<RunArtisanResult>((resolvePromise, rejectPromise) => {
    let child: ReturnType<typeof spawn>;
    try {
      child = spawn(bin, ['artisan', ...args], {
        cwd: root,
        env: process.env,
        stdio: ['ignore', 'pipe', 'pipe'],
      });
    } catch (err) {
      rejectPromise(new ArtisanSpawnError(`failed to spawn ${bin}`, err));
      return;
    }

    let stdout = '';
    let stderr = '';
    let timedOut = false;
    let settled = false;

    const timer = setTimeout(() => {
      timedOut = true;
      try {
        child.kill('SIGKILL');
      } catch {
        // ignore
      }
    }, timeoutMs);
    if (typeof timer.unref === 'function') timer.unref();

    child.stdout?.setEncoding('utf8');
    child.stderr?.setEncoding('utf8');
    child.stdout?.on('data', (chunk: string) => {
      stdout += chunk;
    });
    child.stderr?.on('data', (chunk: string) => {
      stderr += chunk;
    });

    child.on('error', (err) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      rejectPromise(new ArtisanSpawnError(`failed to run ${bin}: ${err.message}`, err));
    });

    child.on('close', (code, signal) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      if (timedOut) {
        rejectPromise(new ArtisanTimeoutError(timeoutMs));
        return;
      }
      const exitCode = typeof code === 'number' ? code : signal ? 1 : 0;
      resolvePromise({ stdout, stderr, exitCode });
    });
  });
}

export type ArtisanRunner = (
  root: string,
  args: string[],
  options?: RunArtisanOptions,
) => Promise<RunArtisanResult>;
