#!/usr/bin/env node
/**
 * Stdio smoke test for the built MCP server.
 *
 * Spawns `dist/index.js`, performs the MCP `initialize` handshake, then
 * issues `tools/list` and asserts every tool registered in
 * `src/tools/index.ts` is exposed by the running server.
 *
 * Exits 0 on success, 1 on any failure (with a diagnostic on stderr).
 * Has no external infra dependencies — safe for CI.
 */
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const PACKAGE_ROOT = resolve(HERE, '..');
const SERVER_ENTRY = resolve(PACKAGE_ROOT, 'dist', 'index.js');

const EXPECTED_TOOLS = [
  'search_docs',
  'get_adr',
  'get_api_reference',
  'list_resources',
  'describe_resource',
  'generate_resource',
  'generate_field',
];

const TIMEOUT_MS = 10_000;

function fail(message) {
  process.stderr.write(`[smoke] FAIL: ${message}\n`);
  process.exit(1);
}

if (!existsSync(SERVER_ENTRY)) {
  fail(`built entry not found at ${SERVER_ENTRY} — run \`pnpm build\` first.`);
}

const child = spawn(process.execPath, [SERVER_ENTRY], {
  stdio: ['pipe', 'pipe', 'pipe'],
  env: { ...process.env, NO_COLOR: '1' },
});

let stderrBuf = '';
child.stderr.on('data', (chunk) => {
  stderrBuf += chunk.toString();
});

let stdoutBuf = '';
const pending = new Map();

child.stdout.on('data', (chunk) => {
  stdoutBuf += chunk.toString();
  let idx = stdoutBuf.indexOf('\n');
  while (idx >= 0) {
    const line = stdoutBuf.slice(0, idx).trim();
    stdoutBuf = stdoutBuf.slice(idx + 1);
    idx = stdoutBuf.indexOf('\n');
    if (!line) continue;
    let msg;
    try {
      msg = JSON.parse(line);
    } catch {
      fail(`malformed JSON from server: ${line}`);
      return;
    }
    if (msg && typeof msg === 'object' && 'id' in msg && pending.has(msg.id)) {
      const resolver = pending.get(msg.id);
      pending.delete(msg.id);
      resolver(msg);
    }
  }
});

child.on('exit', (code, signal) => {
  if (code !== 0 && code !== null) {
    process.stderr.write(`[smoke] server exited unexpectedly: code=${code} signal=${signal}\n`);
    if (stderrBuf) process.stderr.write(`[smoke] server stderr:\n${stderrBuf}\n`);
  }
});

let nextId = 1;
function request(method, params) {
  return new Promise((resolve, reject) => {
    const id = nextId++;
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error(`timeout waiting for ${method} response`));
    }, TIMEOUT_MS);
    pending.set(id, (msg) => {
      clearTimeout(timer);
      if (msg.error) {
        reject(new Error(`${method} returned error: ${JSON.stringify(msg.error)}`));
      } else {
        resolve(msg.result);
      }
    });
    const payload = JSON.stringify({ jsonrpc: '2.0', id, method, params });
    child.stdin.write(`${payload}\n`);
  });
}

function notify(method, params) {
  const payload = JSON.stringify({ jsonrpc: '2.0', method, params });
  child.stdin.write(`${payload}\n`);
}

async function main() {
  const initResult = await request('initialize', {
    protocolVersion: '2024-11-05',
    capabilities: {},
    clientInfo: { name: 'arqel-mcp-smoke', version: '0.0.0' },
  });
  if (!initResult || typeof initResult !== 'object') {
    fail(`initialize returned no result: ${JSON.stringify(initResult)}`);
  }
  if (!initResult.serverInfo?.name) {
    fail(`initialize missing serverInfo.name: ${JSON.stringify(initResult)}`);
  }

  notify('notifications/initialized', {});

  const listResult = await request('tools/list', {});
  const tools = Array.isArray(listResult?.tools) ? listResult.tools : null;
  if (!tools) {
    fail(`tools/list returned no tools array: ${JSON.stringify(listResult)}`);
  }

  const names = new Set(tools.map((t) => t?.name).filter(Boolean));
  const missing = EXPECTED_TOOLS.filter((n) => !names.has(n));
  if (missing.length > 0) {
    fail(`missing tools in server response: ${missing.join(', ')}. Got: ${[...names].join(', ')}`);
  }

  process.stdout.write(
    `[smoke] OK — server "${initResult.serverInfo.name}" exposes ${tools.length} tools (${EXPECTED_TOOLS.length} expected).\n`,
  );
  child.stdin.end();
  child.kill('SIGTERM');
  process.exit(0);
}

main().catch((err) => {
  process.stderr.write(`[smoke] error: ${err instanceof Error ? err.message : String(err)}\n`);
  if (stderrBuf) process.stderr.write(`[smoke] server stderr:\n${stderrBuf}\n`);
  child.kill('SIGTERM');
  process.exit(1);
});
