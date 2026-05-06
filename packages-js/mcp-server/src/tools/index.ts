export type {
  DescribeResourceErrorResponse,
  DescribeResourceFoundResponse,
  DescribeResourceInput,
  DescribeResourceMissingResponse,
  DescribeResourceResponse,
} from './describe-resource.js';
export {
  createDescribeResourceTool,
  DescribeResourceInputSchema,
  describeResource,
  describeResourceTool,
} from './describe-resource.js';
export type {
  GenerateFieldErrorResponse,
  GenerateFieldInput,
  GenerateFieldOkResponse,
  GenerateFieldResponse,
} from './generate-field.js';
export {
  GenerateFieldInputSchema,
  generateField,
  generateFieldTool,
} from './generate-field.js';
export type {
  GenerateResourceErrorResponse,
  GenerateResourceInput,
  GenerateResourceOkResponse,
  GenerateResourceResponse,
} from './generate-resource.js';
export {
  GenerateResourceInputSchema,
  generateResource,
  generateResourceTool,
} from './generate-resource.js';
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
  ListResourcesErrorResponse,
  ListResourcesInput,
  ListResourcesOkResponse,
  ListResourcesResponse,
} from './list-resources.js';
export {
  createListResourcesTool,
  ListResourcesInputSchema,
  listResources,
  listResourcesTool,
} from './list-resources.js';
export type {
  SearchDocsInput,
  SearchDocsResponse,
  SearchDocsResult,
  ToolModule,
} from './search-docs.js';
export { SearchDocsInputSchema, searchDocs, searchDocsTool } from './search-docs.js';

import { describeResourceTool } from './describe-resource.js';
import { generateFieldTool } from './generate-field.js';
import { generateResourceTool } from './generate-resource.js';
import { getAdrTool } from './get-adr.js';
import { getApiReferenceTool } from './get-api-reference.js';
import { listResourcesTool } from './list-resources.js';
import { searchDocsTool } from './search-docs.js';

/**
 * Canonical list of tools registered with the MCP server.
 * New tools should be appended here and follow the same
 * `{ definition, handle }` shape exported by `searchDocsTool`.
 */
export const tools = [
  searchDocsTool,
  getAdrTool,
  getApiReferenceTool,
  listResourcesTool,
  describeResourceTool,
  generateResourceTool,
  generateFieldTool,
] as const;
