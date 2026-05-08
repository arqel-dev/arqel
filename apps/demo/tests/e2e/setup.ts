import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const APP_ROOT = join(__dirname, '..', '..');

/**
 * Run an artisan command synchronously without spawning a shell.
 * All arguments are hardcoded — no user input flows through.
 */
function runArtisan(args: string[]): void {
  execFileSync('php', ['artisan', ...args], {
    cwd: APP_ROOT,
    stdio: 'pipe',
  });
}

/**
 * Reset the demo database to a known state for each E2E test.
 *
 * Runs `migrate:fresh --seed --force` then re-creates the admin
 * user (the seeder doesn't, since arqel:make-user is the canonical
 * path used by all sandbox upgrades in v0.9.x and v0.10.0).
 */
export function resetDatabase(): void {
  runArtisan(['migrate:fresh', '--seed', '--force']);
  runArtisan([
    'arqel:make-user',
    '--email=admin@arqel.test',
    '--password=password',
    '--name=Admin',
  ]);
}
