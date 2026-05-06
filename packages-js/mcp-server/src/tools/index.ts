export type { GetAdrErrorCode, GetAdrInput, GetAdrResponse } from './get-adr.js';
export { GetAdrInputSchema, getAdr, getAdrTool } from './get-adr.js';
export type {
  SearchDocsInput,
  SearchDocsResponse,
  SearchDocsResult,
  ToolModule,
} from './search-docs.js';
export { SearchDocsInputSchema, searchDocs, searchDocsTool } from './search-docs.js';

import { getAdrTool } from './get-adr.js';
import { searchDocsTool } from './search-docs.js';

/**
 * Canonical list of tools registered with the MCP server.
 * New tools (MCP-005+) should be appended here and follow the same
 * `{ definition, handle }` shape exported by `searchDocsTool`.
 */
export const tools = [searchDocsTool, getAdrTool] as const;
