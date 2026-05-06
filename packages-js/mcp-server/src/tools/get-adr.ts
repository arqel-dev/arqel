import { readFileSync } from 'node:fs';
import { z } from 'zod';
import { type PlanningFileResolution, resolvePlanningFile } from '../planning/loader.js';
import { normalizeAdrId, type ParsedAdr, parseAdrs } from '../planning/parse-adrs.js';

const ADRS_FILENAME = '03-adrs.md';

export const GetAdrInputSchema = z.object({
  id: z.string().min(1, 'id must be a non-empty string'),
});

export type GetAdrInput = z.infer<typeof GetAdrInputSchema>;

export type GetAdrErrorCode = 'ADR_NOT_FOUND' | 'ADRS_FILE_NOT_FOUND' | 'INVALID_ID';

export interface GetAdrResponse {
  adr?: ParsedAdr;
  error?: { code: GetAdrErrorCode; message: string };
}

interface CacheEntry {
  resolution: PlanningFileResolution;
  adrs: Map<string, ParsedAdr>;
}

interface GetAdrOptions {
  /** Pre-resolved planning file. When omitted the loader tries bundled → monorepo. */
  resolution?: PlanningFileResolution | null;
  /** Skip caching (useful for tests). */
  noCache?: boolean;
}

let cached: CacheEntry | null = null;

/** Reset the in-process ADR cache. Exposed for tests. */
export function resetGetAdrCache(): void {
  cached = null;
}

function getAdrIndex(options: GetAdrOptions): CacheEntry | null {
  if (!options.noCache && cached) return cached;
  const resolution =
    'resolution' in options ? options.resolution : resolvePlanningFile(ADRS_FILENAME);
  if (!resolution) return null;
  let raw: string;
  try {
    raw = readFileSync(resolution.path, 'utf-8');
  } catch {
    return null;
  }
  const entry: CacheEntry = { resolution, adrs: parseAdrs(raw) };
  if (!options.noCache) cached = entry;
  return entry;
}

export function getAdr(input: GetAdrInput, options: GetAdrOptions = {}): GetAdrResponse {
  const normalized = normalizeAdrId(input.id);
  if (normalized === null) {
    return {
      error: {
        code: 'INVALID_ID',
        message: `Invalid ADR id: ${JSON.stringify(input.id)}. Expected forms: "001", "1", "ADR-001".`,
      },
    };
  }

  const entry = getAdrIndex(options);
  if (!entry) {
    return {
      error: {
        code: 'ADRS_FILE_NOT_FOUND',
        message:
          'ADRs file not found. Looked for bundled `<package>/planning/03-adrs.md` and monorepo `PLANNING/03-adrs.md`.',
      },
    };
  }

  const adr = entry.adrs.get(normalized);
  if (!adr) {
    return {
      error: {
        code: 'ADR_NOT_FOUND',
        message: `ADR-${normalized} not found in ${ADRS_FILENAME}.`,
      },
    };
  }

  return { adr };
}

/* ------------------------------------------------------------------ *
 * MCP tool definition + handler
 * ------------------------------------------------------------------ */

export const getAdrTool = {
  definition: {
    name: 'get_adr',
    description:
      'Fetch a single Architecture Decision Record (ADR) from PLANNING/03-adrs.md by id. Accepts "001", "1" or "ADR-001" (case-insensitive).',
    inputSchema: {
      type: 'object',
      properties: {
        id: {
          type: 'string',
          description:
            'ADR identifier. Accepted forms: "001", "1", "ADR-001", "adr-1" (case-insensitive).',
        },
      },
      required: ['id'],
      additionalProperties: false,
    },
  },
  handle(rawInput: unknown): {
    content: Array<{ type: 'text'; text: string }>;
    isError?: boolean;
  } {
    const parsed = GetAdrInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      return {
        content: [
          {
            type: 'text',
            text: `Invalid arguments for get_adr: ${parsed.error.message}`,
          },
        ],
        isError: true,
      };
    }
    const response = getAdr(parsed.data);
    if (response.error) {
      return {
        content: [
          {
            type: 'text',
            text: JSON.stringify(response, null, 2),
          },
        ],
        isError: true,
      };
    }
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

export type GetAdrTool = typeof getAdrTool;
