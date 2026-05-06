import { readFileSync } from 'node:fs';
import { z } from 'zod';

import { type PlanningFileResolution, resolvePlanningFile } from '../planning/loader.js';
import {
  type ApiEntry,
  type ApiLanguage,
  parseApiReference,
} from '../planning/parse-api-reference.js';

const PHP_FILENAME = '05-api-php.md';
const REACT_FILENAME = '06-api-react.md';

export const GetApiReferenceInputSchema = z.object({
  symbol: z.string().min(1, 'symbol must be a non-empty string'),
  language: z.enum(['php', 'react']).optional(),
});

export type GetApiReferenceInput = z.infer<typeof GetApiReferenceInputSchema>;

export type GetApiReferenceErrorCode = 'API_FILES_NOT_FOUND';

export interface ApiCandidate {
  symbol: string;
  language: ApiLanguage;
  headingPath: string;
  file: string;
}

export interface GetApiReferenceResponse {
  match: 'exact' | 'fuzzy' | 'none';
  entry?: ApiEntry;
  candidates?: ApiCandidate[];
  error?: { code: GetApiReferenceErrorCode; message: string };
}

interface CacheEntry {
  php: ApiEntry[] | null;
  react: ApiEntry[] | null;
}

interface GetApiReferenceOptions {
  /** Pre-resolved PHP planning file. `undefined` triggers loader; `null` forces "missing". */
  phpResolution?: PlanningFileResolution | null;
  /** Pre-resolved React planning file. */
  reactResolution?: PlanningFileResolution | null;
  /** Skip caching (useful for tests). */
  noCache?: boolean;
}

let cached: CacheEntry | null = null;

/** Reset the in-process API-reference cache. Exposed for tests. */
export function resetGetApiReferenceCache(): void {
  cached = null;
}

function loadEntries(options: GetApiReferenceOptions, language: ApiLanguage): ApiEntry[] | null {
  const filename = language === 'php' ? PHP_FILENAME : REACT_FILENAME;
  const optKey = language === 'php' ? 'phpResolution' : 'reactResolution';
  const resolution = optKey in options ? (options[optKey] ?? null) : resolvePlanningFile(filename);
  if (!resolution) return null;
  let raw: string;
  try {
    raw = readFileSync(resolution.path, 'utf-8');
  } catch {
    return null;
  }
  return parseApiReference(raw, language);
}

function getApiIndex(options: GetApiReferenceOptions): CacheEntry {
  if (!options.noCache && cached) return cached;
  const entry: CacheEntry = {
    php: loadEntries(options, 'php'),
    react: loadEntries(options, 'react'),
  };
  if (!options.noCache) cached = entry;
  return entry;
}

/**
 * Pure ranker for fuzzy candidates.
 *
 * Sort key:
 *   1. Exact prefix match wins over middle match.
 *   2. Shorter symbol name wins.
 *   3. Preferred language first.
 *   4. Tie-broken by alphabetic symbol order for determinism.
 *
 * The query is matched case-insensitively as a substring against `symbol`.
 * Returns up to 5 candidates.
 */
export function rankCandidates(
  query: string,
  entries: ApiEntry[],
  languagePreference?: ApiLanguage,
): ApiEntry[] {
  const q = query.toLowerCase();
  const matches = entries.filter((e) => e.symbol.toLowerCase().includes(q));
  const preferred: ApiLanguage = languagePreference ?? 'php';
  matches.sort((a, b) => {
    const ap = a.symbol.toLowerCase().startsWith(q) ? 0 : 1;
    const bp = b.symbol.toLowerCase().startsWith(q) ? 0 : 1;
    if (ap !== bp) return ap - bp;
    if (a.symbol.length !== b.symbol.length) return a.symbol.length - b.symbol.length;
    if (a.language !== b.language) {
      if (a.language === preferred) return -1;
      if (b.language === preferred) return 1;
    }
    return a.symbol.localeCompare(b.symbol);
  });
  return matches.slice(0, 5);
}

function toCandidate(e: ApiEntry): ApiCandidate {
  return {
    symbol: e.symbol,
    language: e.language,
    headingPath: e.headingPath,
    file: e.file,
  };
}

export function getApiReference(
  input: GetApiReferenceInput,
  options: GetApiReferenceOptions = {},
): GetApiReferenceResponse {
  const index = getApiIndex(options);
  if (index.php === null && index.react === null) {
    return {
      match: 'none',
      candidates: [],
      error: {
        code: 'API_FILES_NOT_FOUND',
        message:
          'API reference files not found. Looked for bundled `<package>/planning/{05-api-php,06-api-react}.md` and monorepo `PLANNING/` equivalents.',
      },
    };
  }

  const lang = input.language;
  const pool: ApiEntry[] = [];
  if (lang === 'php') {
    if (index.php) pool.push(...index.php);
  } else if (lang === 'react') {
    if (index.react) pool.push(...index.react);
  } else {
    if (index.php) pool.push(...index.php);
    if (index.react) pool.push(...index.react);
  }

  const target = input.symbol.toLowerCase();

  // Exact (case-insensitive) match. If multiple, prefer language preference.
  const exactMatches = pool.filter((e) => e.symbol.toLowerCase() === target);
  if (exactMatches.length > 0) {
    const preferred: ApiLanguage = lang ?? 'php';
    exactMatches.sort((a, b) => {
      if (a.language !== b.language) {
        if (a.language === preferred) return -1;
        if (b.language === preferred) return 1;
      }
      return a.symbol.length - b.symbol.length;
    });
    const winner = exactMatches[0];
    if (winner) {
      return { match: 'exact', entry: winner };
    }
  }

  const ranked = rankCandidates(input.symbol, pool, lang);
  if (ranked.length === 0) {
    return { match: 'none', candidates: [] };
  }
  return { match: 'fuzzy', candidates: ranked.map(toCandidate) };
}

/* ------------------------------------------------------------------ *
 * MCP tool definition + handler
 * ------------------------------------------------------------------ */

export const getApiReferenceTool = {
  definition: {
    name: 'get_api_reference',
    description:
      'Look up Arqel API documentation for a named symbol from PLANNING/05-api-php.md and PLANNING/06-api-react.md. Returns an exact match or up to 5 fuzzy candidates.',
    inputSchema: {
      type: 'object',
      properties: {
        symbol: {
          type: 'string',
          description: 'Symbol or class/function name to look up (e.g. "Resource", "useResource").',
        },
        language: {
          type: 'string',
          enum: ['php', 'react'],
          description: 'Optional filter restricting the search to one of the two API files.',
        },
      },
      required: ['symbol'],
      additionalProperties: false,
    },
  },
  handle(rawInput: unknown): {
    content: Array<{ type: 'text'; text: string }>;
    isError?: boolean;
  } {
    const parsed = GetApiReferenceInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      return {
        content: [
          {
            type: 'text',
            text: `Invalid arguments for get_api_reference: ${parsed.error.message}`,
          },
        ],
        isError: true,
      };
    }
    const response = getApiReference(parsed.data);
    if (response.error) {
      return {
        content: [{ type: 'text', text: JSON.stringify(response, null, 2) }],
        isError: true,
      };
    }
    return {
      content: [{ type: 'text', text: JSON.stringify(response, null, 2) }],
    };
  },
} as const;

export type GetApiReferenceTool = typeof getApiReferenceTool;
