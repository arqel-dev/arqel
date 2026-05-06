import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

import packageJson from '../package.json' with { type: 'json' };

export interface CreateServerOptions {
  name?: string;
  version?: string;
}

export function createServer(options: CreateServerOptions = {}): Server {
  return new Server(
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
}

export async function runServer(server: Server): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}
