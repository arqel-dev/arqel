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
 * Reset the showcase database to a known state for each E2E test.
 *
 * `migrate:fresh --seed` wipes everything and seeds the showcase domain:
 * 2 tenants (Acme current, Globex), ~5 authors, ~10 categories, ~30 posts
 * split across the two tenants, ~8 tickets, 3 settings, plus the admin
 * (admin@arqel.test / password) attached to both tenants with Acme as the
 * current tenant. The seeder owns admin creation here (firstOrCreate), so
 * we do NOT call `arqel:make-user`: it exits non-zero on a duplicate
 * email, which would abort this helper via execFileSync.
 */
export function resetDatabase(): void {
  runArtisan(['migrate:fresh', '--seed', '--force']);
}
