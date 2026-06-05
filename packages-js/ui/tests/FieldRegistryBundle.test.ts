/**
 * Regression test for #45 — FieldRegistry duplicated across tsup entries.
 *
 * With `splitting: false`, tsup inlined the module-level FieldRegistry Map
 * (`src/form/FieldRegistry.tsx`) into EVERY entry bundle that pulled it in
 * (form.js, pages.js, action.js, index.js). Fields registered through the
 * `./form` entry (used by `@arqel-dev/fields/register`) then lived in a
 * different Map than the one the `./pages` renderer read from, so advanced
 * fields silently fell back to native HTML inputs and boolean/datetime
 * submits 422'd.
 *
 * Enabling `splitting: true` hoists FieldRegistry into a single shared chunk,
 * so the registry definition appears EXACTLY ONCE across all emitted bundles.
 *
 * This test asserts against the BUILT dist (the in-process FormRenderer test
 * shares one Map and cannot catch a bundling regression). It therefore
 * requires a prior build: `pnpm --filter @arqel-dev/ui build`. CI builds all
 * packages before vitest (see .github/workflows/ci.yml `test-js` job), so the
 * dist is present there. Locally, if dist is absent the test skips with a
 * clear message rather than producing a false negative.
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const distDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'dist');

// Exact emitted shape of `const registry = new Map<...>()` from
// src/form/FieldRegistry.tsx after esbuild transform.
const REGISTRY_PATTERN = 'registry = /* @__PURE__ */ new Map()';

function countRegistryInstances(): { total: number; perFile: Record<string, number> } {
  const perFile: Record<string, number> = {};
  let total = 0;
  for (const file of readdirSync(distDir)) {
    if (!file.endsWith('.js')) continue;
    const source = readFileSync(join(distDir, file), 'utf8');
    // Only count occurrences that belong to the FieldRegistry module: the
    // pattern always co-locates with getFieldComponent in the same chunk.
    if (!source.includes('function getFieldComponent')) continue;
    const matches = source.split(REGISTRY_PATTERN).length - 1;
    if (matches > 0) {
      perFile[file] = matches;
      total += matches;
    }
  }
  return { total, perFile };
}

describe('FieldRegistry bundle split (#45)', () => {
  it.skipIf(!existsSync(distDir))(
    'inlines the FieldRegistry Map into exactly one shared chunk',
    () => {
      const { total, perFile } = countRegistryInstances();
      expect(
        total,
        `FieldRegistry Map must exist exactly once across dist/*.js so that ` +
          `field registration is a single shared singleton. Found ${total} ` +
          `instance(s): ${JSON.stringify(perFile)}. If >1, tsup is duplicating ` +
          `the module — ensure splitting:true in tsup.config.ts.`,
      ).toBe(1);
    },
  );
});
