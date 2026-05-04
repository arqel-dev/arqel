#!/usr/bin/env tsx
/**
 * Sweep audit a11y — varre arquivos `.tsx` em apps/ procurando por padrões suspeitos.
 *
 * Defensive: imprime findings, não falha o processo. Heurístico apenas (regex);
 * confirme manualmente cada item antes de corrigir.
 *
 * Uso: pnpm --filter @arqel-dev/a11y audit:scan -- [glob-root]
 */
import { readdirSync, readFileSync, type Stats, statSync } from 'node:fs';
import { join, relative } from 'node:path';

interface Finding {
  file: string;
  line: number;
  rule: string;
  snippet: string;
}

const ROOT = process.argv[2] ?? join(process.cwd(), 'apps');

const RULES: Array<{ id: string; pattern: RegExp; message: string }> = [
  {
    id: 'img-no-alt',
    pattern: /<img\b(?![^>]*\balt=)/i,
    message: '<img> sem atributo alt',
  },
  {
    id: 'icon-button-no-aria',
    pattern: /<button\b(?![^>]*\baria-label=)(?![^>]*>[^<]*[a-zA-Z0-9])(?=[^>]*>\s*<)/i,
    message: 'botão com filho-elemento sem aria-label (provável icon-only)',
  },
  {
    id: 'input-no-id',
    pattern: /<input\b(?![^>]*\bid=)(?![^>]*type=["']hidden["'])(?![^>]*aria-label=)/i,
    message: '<input> sem id e sem aria-label (impossível associar <label>)',
  },
  {
    id: 'a-no-text',
    pattern: /<a\b[^>]*>\s*<\/a>/i,
    message: '<a> vazio (sem texto acessível)',
  },
  {
    id: 'tabindex-positive',
    pattern: /tabIndex=\{?["']?([1-9])/,
    message: 'tabIndex positivo (anti-pattern — quebra ordem natural)',
  },
];

function* walk(dir: string): Generator<string> {
  let entries: string[];
  try {
    entries = readdirSync(dir);
  } catch {
    return;
  }
  for (const entry of entries) {
    if (entry === 'node_modules' || entry === 'dist' || entry === '.next') continue;
    const full = join(dir, entry);
    let st: Stats;
    try {
      st = statSync(full);
    } catch {
      continue;
    }
    if (st.isDirectory()) {
      yield* walk(full);
    } else if (full.endsWith('.tsx')) {
      yield full;
    }
  }
}

function scanFile(file: string): Finding[] {
  let content: string;
  try {
    content = readFileSync(file, 'utf8');
  } catch {
    return [];
  }
  const lines = content.split('\n');
  const findings: Finding[] = [];
  lines.forEach((line, index) => {
    for (const rule of RULES) {
      if (rule.pattern.test(line)) {
        findings.push({
          file,
          line: index + 1,
          rule: rule.id,
          snippet: line.trim().slice(0, 140),
        });
      }
    }
  });
  return findings;
}

function main(): void {
  const all: Finding[] = [];
  for (const file of walk(ROOT)) {
    all.push(...scanFile(file));
  }
  if (all.length === 0) {
    process.stdout.write('a11y audit: 0 findings\n');
    return;
  }
  process.stdout.write(`a11y audit: ${all.length} findings\n\n`);
  for (const f of all) {
    const rel = relative(process.cwd(), f.file);
    process.stdout.write(`${rel}:${f.line} — [${f.rule}] ${f.snippet}\n`);
  }
}

main();
