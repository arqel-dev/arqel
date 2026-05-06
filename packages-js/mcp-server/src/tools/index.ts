export type {
  SearchDocsInput,
  SearchDocsResponse,
  SearchDocsResult,
  ToolModule,
} from './search-docs.js';
export { SearchDocsInputSchema, searchDocs, searchDocsTool } from './search-docs.js';

import { searchDocsTool } from './search-docs.js';

/**
 * Canonical list of tools registered with the MCP server.
 * New tools (MCP-004+) should be appended here and follow the same
 * `{ definition, handle }` shape exported by `searchDocsTool`.
 */
export const tools = [searchDocsTool] as const;
