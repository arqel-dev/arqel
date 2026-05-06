import { z } from 'zod';

import {
  buildIndex,
  type DocSection,
  type DocsResolution,
  resolveDocsDir,
} from '../docs/loader.js';

const DEFAULT_LIMIT = 10;
const MAX_LIMIT = 25;

export const SearchDocsInputSchema = z.object({
  query: z.string().min(1, 'query must be a non-empty string'),
  limit: z.number().int().positive().max(MAX_LIMIT).optional(),
});

export type SearchDocsInput = z.infer<typeof SearchDocsInputSchema>;

export interface SearchDocsResult {
  path: string;
  heading: string;
  score: number;
  excerpt: string;
}

export interface SearchDocsResponse {
  results: SearchDocsResult[];
  error?: { code: 'DOCS_NOT_FOUND'; message: string };
}

interface CacheEntry {
  resolution: DocsResolution;
  index: DocSection[];
}

interface SearchDocsOptions {
  /** Pre-resolved docs directory. When omitted the loader tries bundled → monorepo. */
  resolution?: DocsResolution | null;
  /** Skip caching (useful for tests). */
  noCache?: boolean;
}

let cached: CacheEntry | null = null;

/** Reset the in-process index cache. Exposed for tests. */
export function resetSearchDocsCache(): void {
  cached = null;
}

function getIndex(options: SearchDocsOptions): CacheEntry | null {
  if (!options.noCache && cached) return cached;
  const resolution = 'resolution' in options ? options.resolution : resolveDocsDir();
  if (!resolution) return null;
  const entry: CacheEntry = { resolution, index: buildIndex(resolution) };
  if (!options.noCache) cached = entry;
  return entry;
}

function countOccurrences(haystack: string, needle: string): number {
  if (needle.length === 0) return 0;
  let count = 0;
  let from = 0;
  while (true) {
    const idx = haystack.indexOf(needle, from);
    if (idx === -1) break;
    count += 1;
    from = idx + needle.length;
  }
  return count;
}

function buildExcerpt(body: string, query: string): string {
  const lower = body.toLowerCase();
  const q = query.toLowerCase();
  const idx = lower.indexOf(q);
  const radius = 90;
  if (idx === -1) {
    return body.slice(0, 200).trim();
  }
  const start = Math.max(0, idx - radius);
  const end = Math.min(body.length, idx + q.length + radius);
  const slice = body.slice(start, end);
  const before = slice.slice(0, idx - start);
  const match = body.slice(idx, idx + query.length);
  const after = slice.slice(idx - start + query.length);
  const prefix = start > 0 ? '…' : '';
  const suffix = end < body.length ? '…' : '';
  return `${prefix}${before}**${match}**${after}${suffix}`.replace(/\s+/g, ' ').trim();
}

export function searchDocs(
  input: SearchDocsInput,
  options: SearchDocsOptions = {},
): SearchDocsResponse {
  const { query } = input;
  const requested = input.limit ?? DEFAULT_LIMIT;
  const limit = Math.min(MAX_LIMIT, Math.max(1, requested));

  const entry = getIndex(options);
  if (!entry) {
    return {
      results: [],
      error: {
        code: 'DOCS_NOT_FOUND',
        message:
          'Docs corpus not found. Looked for bundled `<package>/docs` and monorepo `apps/docs`.',
      },
    };
  }

  const needle = query.toLowerCase();
  const scored: SearchDocsResult[] = [];
  for (const section of entry.index) {
    const headingHits = countOccurrences(section.heading.toLowerCase(), needle);
    const bodyHits = countOccurrences(section.body.toLowerCase(), needle);
    if (headingHits === 0 && bodyHits === 0) continue;
    const score = headingHits * 3 + bodyHits;
    scored.push({
      path: section.path,
      heading: section.heading,
      score,
      excerpt: buildExcerpt(section.body, query),
    });
  }

  scored.sort((a, b) => b.score - a.score);
  return { results: scored.slice(0, limit) };
}

/* ------------------------------------------------------------------ *
 * MCP tool definition + handler
 * ------------------------------------------------------------------ */

export const searchDocsTool = {
  definition: {
    name: 'search_docs',
    description:
      'Search the Arqel documentation corpus (Markdown). Returns the top matching sections with file path, heading and an excerpt around the first match.',
    inputSchema: {
      type: 'object',
      properties: {
        query: {
          type: 'string',
          description: 'Substring to search for (case-insensitive).',
        },
        limit: {
          type: 'integer',
          minimum: 1,
          maximum: MAX_LIMIT,
          description: `Maximum number of results to return. Default ${DEFAULT_LIMIT}, max ${MAX_LIMIT}.`,
        },
      },
      required: ['query'],
      additionalProperties: false,
    },
  },
  handle(rawInput: unknown): {
    content: Array<{ type: 'text'; text: string }>;
    isError?: boolean;
  } {
    const parsed = SearchDocsInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      return {
        content: [
          {
            type: 'text',
            text: `Invalid arguments for search_docs: ${parsed.error.message}`,
          },
        ],
        isError: true,
      };
    }
    const response = searchDocs(parsed.data);
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(response, null, 2),
        },
      ],
    };
  },
} as const;

export type ToolModule = typeof searchDocsTool;
