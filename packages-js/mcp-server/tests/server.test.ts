import { describe, expect, it } from 'vitest';

import { createServer } from '../src/server.js';

describe('@arqel-dev/mcp-server', () => {
  it('creates a server instance with package metadata', () => {
    const server = createServer();
    expect(server).toBeDefined();
  });

  it('accepts overrides for name and version', () => {
    const server = createServer({ name: 'custom', version: '9.9.9' });
    expect(server).toBeDefined();
  });
});
