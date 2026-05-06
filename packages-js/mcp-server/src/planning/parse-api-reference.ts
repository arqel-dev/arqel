/**
 * Pure parser for `PLANNING/05-api-php.md` and `PLANNING/06-api-react.md`.
 *
 * Both files share a 2-level structure:
 *   - `## <N>. <Category>` — top-level (often a concrete symbol).
 *   - `### <N>.<M> <Subtopic>` — sub-symbol or generic descriptor.
 *
 * `parseApiReference()` walks the file, classifies each `###` heading as
 * concrete or generic via `isGenericSubheading()`, and emits one flat list
 * of `ApiEntry` records. Both the parent `##` symbol and its concrete `###`
 * children are emitted (allowing exact lookups for either form).
 *
 * Generic `###` headings are folded into the parent's body and are NOT
 * emitted as their own entry.
 */

export type ApiLanguage = 'php' | 'react';

export interface ApiEntry {
  /** Symbol name extracted from the heading (e.g. `Resource`, `useResource`). */
  symbol: string;
  /** Source language file the entry came from. */
  language: ApiLanguage;
  /** Pretty heading path (e.g. `Resource > Resource discovery`). */
  headingPath: string;
  /** Markdown body for the entry, with the heading line itself stripped. */
  body: string;
  /** Source filename (e.g. `05-api-php.md`). */
  file: string;
  /** 1-based source line of the heading. */
  line: number;
}

/**
 * Strips a leading `N.` or `N.M ` numbering and surrounding whitespace from
 * a heading text, returning the bare symbol/description.
 */
export function stripHeadingNumbering(text: string): string {
  return text.replace(/^\s*\d+(?:\.\d+)*\.?\s*/, '').trim();
}

const GENERIC_HEADINGS = new Set<string>([
  'base class',
  'base api',
  'examples',
  'example',
  'config',
  'automatic',
  'manual',
]);

/**
 * Decides whether a `###` subheading should be folded into its parent rather
 * than indexed as its own symbol entry.
 *
 * A heading is considered generic when:
 *   1. Its lowercased trimmed text is in the small known-generic set
 *      (`base class`, `base api`, `examples`, `config`, `automatic`,
 *      `manual`), OR
 *   2. It starts with a lowercase letter AND contains no uppercase letters
 *      (a multi-word descriptive phrase like `examples` rather than a
 *      camelCase identifier such as `useResource`).
 *
 * Camel-case identifiers (lowercase first char + later uppercase letter)
 * are intentionally treated as concrete: `useResource`, `useArqelForm`.
 */
export function isGenericSubheading(text: string): boolean {
  const trimmed = stripHeadingNumbering(text);
  if (trimmed === '') return true;
  if (GENERIC_HEADINGS.has(trimmed.toLowerCase())) return true;
  const first = trimmed[0];
  if (first && first === first.toLowerCase() && first !== first.toUpperCase()) {
    if (!/[A-Z]/.test(trimmed)) return true;
  }
  return false;
}

interface RawHeading {
  level: 2 | 3;
  text: string;
  line: number;
  index: number;
}

const H2 = /^##\s+(.+?)\s*#*\s*$/;
const H3 = /^###\s+(.+?)\s*#*\s*$/;

/**
 * Parses an API-reference markdown file into a flat list of entries.
 */
export function parseApiReference(content: string, language: ApiLanguage): ApiEntry[] {
  const file = language === 'php' ? '05-api-php.md' : '06-api-react.md';
  const lines = content.split(/\r?\n/);

  const headings: RawHeading[] = [];
  let inFence = false;
  for (let i = 0; i < lines.length; i += 1) {
    const line = lines[i] ?? '';
    if (/^\s*```/.test(line)) {
      inFence = !inFence;
      continue;
    }
    if (inFence) continue;
    const m2 = H2.exec(line);
    if (m2) {
      headings.push({ level: 2, text: (m2[1] ?? '').trim(), line: i + 1, index: i });
      continue;
    }
    const m3 = H3.exec(line);
    if (m3) {
      headings.push({ level: 3, text: (m3[1] ?? '').trim(), line: i + 1, index: i });
    }
  }

  const entries: ApiEntry[] = [];

  for (let i = 0; i < headings.length; i += 1) {
    const h = headings[i];
    if (!h) continue;
    if (h.level !== 2) continue;

    const parentSymbol = stripHeadingNumbering(h.text);
    if (parentSymbol === '') continue;

    let nextH2Pos = -1;
    for (let k = i + 1; k < headings.length; k += 1) {
      const next = headings[k];
      if (next && next.level === 2) {
        nextH2Pos = next.index;
        break;
      }
    }
    const sectionEnd = nextH2Pos === -1 ? lines.length : nextH2Pos;

    const parentBody = lines
      .slice(h.index + 1, sectionEnd)
      .join('\n')
      .replace(/^\n+|\n+$/g, '');

    entries.push({
      symbol: parentSymbol,
      language,
      headingPath: parentSymbol,
      body: parentBody,
      file,
      line: h.line,
    });

    for (let j = i + 1; j < headings.length; j += 1) {
      const child = headings[j];
      if (!child) continue;
      if (child.level === 2) break;
      if (child.level !== 3) continue;
      if (isGenericSubheading(child.text)) continue;

      const childSymbol = stripHeadingNumbering(child.text);
      if (childSymbol === '') continue;

      let endIdx = sectionEnd;
      for (let k = j + 1; k < headings.length; k += 1) {
        const next = headings[k];
        if (!next) continue;
        if (next.index >= sectionEnd) break;
        endIdx = next.index;
        break;
      }
      const childBody = lines
        .slice(child.index + 1, endIdx)
        .join('\n')
        .replace(/^\n+|\n+$/g, '');

      entries.push({
        symbol: childSymbol,
        language,
        headingPath: `${parentSymbol} > ${childSymbol}`,
        body: childBody,
        file,
        line: child.line,
      });
    }
  }

  return entries;
}
