export type { GetAdrErrorCode, GetAdrInput, GetAdrResponse } from './get-adr.js';
export { GetAdrInputSchema, getAdr, getAdrTool } from './get-adr.js';
export type {
  ApiCandidate,
  GetApiReferenceErrorCode,
  GetApiReferenceInput,
  GetApiReferenceResponse,
} from './get-api-reference.js';
export {
  GetApiReferenceInputSchema,
  getApiReference,
  getApiReferenceTool,
  rankCandidates,
} from './get-api-reference.js';
export type {
  SearchDocsInput,
  SearchDocsResponse,
  SearchDocsResult,
  ToolModule,
} from './search-docs.js';
export { SearchDocsInputSchema, searchDocs, searchDocsTool } from './search-docs.js';

import { getAdrTool } from './get-adr.js';
import { getApiReferenceTool } from './get-api-reference.js';
import { searchDocsTool } from './search-docs.js';

/**
 * Canonical list of tools registered with the MCP server.
 * New tools should be appended here and follow the same
 * `{ definition, handle }` shape exported by `searchDocsTool`.
 */
export const tools = [searchDocsTool, getAdrTool, getApiReferenceTool] as const;
