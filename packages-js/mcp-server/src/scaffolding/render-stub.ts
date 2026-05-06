/**
 * Tiny `{{token}}` template renderer.
 *
 * Defensive on both sides:
 * - Throws if the template references a token not in `tokens`.
 * - Throws if the rendered output still contains an unfilled `{{...}}`.
 *
 * No conditionals, no loops, no escaping — keep it boring so the PHP
 * stubs and the JS stubs render identically.
 */
const TOKEN_RE = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/g;

export function renderStub(template: string, tokens: Record<string, string>): string {
  const seen = new Set<string>();
  const output = template.replace(TOKEN_RE, (_match, name: string) => {
    seen.add(name);
    if (!Object.hasOwn(tokens, name)) {
      throw new Error(`renderStub: unknown token "{{${name}}}" — not provided in tokens`);
    }
    return tokens[name] ?? '';
  });
  const stillHasToken = /\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/.exec(output);
  if (stillHasToken) {
    throw new Error(`renderStub: unfilled token "${stillHasToken[0]}" in output`);
  }
  for (const key of Object.keys(tokens)) {
    if (!seen.has(key)) {
      throw new Error(`renderStub: token "${key}" was provided but not used in template`);
    }
  }
  return output;
}
