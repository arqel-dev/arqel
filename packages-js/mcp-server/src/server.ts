import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';

import packageJson from '../package.json' with { type: 'json' };
import { tools } from './tools/index.js';

export interface CreateServerOptions {
  name?: string;
  version?: string;
}

export function createServer(options: CreateServerOptions = {}): Server {
  const server = new Server(
    {
      name: options.name ?? packageJson.name,
      version: options.version ?? packageJson.version,
    },
    {
      capabilities: {
        tools: {},
      },
    },
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: tools.map((tool) => tool.definition),
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;
    const tool = tools.find((candidate) => candidate.definition.name === name);
    if (!tool) {
      return {
        content: [{ type: 'text', text: `Unknown tool: ${name}` }],
        isError: true,
      };
    }
    const result = await (
      tool.handle as (input: unknown) => Promise<{
        content: Array<{ type: 'text'; text: string }>;
        isError?: boolean;
      }>
    )(args ?? {});
    return result;
  });

  return server;
}

export async function runServer(server: Server): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}
