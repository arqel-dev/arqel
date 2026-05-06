import { createServer, runServer } from './server.js';

export { createServer, runServer };

const isDirectInvocation = (() => {
  if (typeof process === 'undefined' || !process.argv[1]) return false;
  const entry = process.argv[1];
  return entry.endsWith('arqel-mcp') || entry.endsWith('mcp-server/dist/index.js');
})();

if (isDirectInvocation) {
  runServer(createServer()).catch((error: unknown) => {
    process.stderr.write(`[arqel-mcp] fatal: ${(error as Error).message}\n`);
    process.exit(1);
  });
}
