import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { indentSnippet, RenderFieldValidationError, renderField } from './render-field.js';
import { renderStub } from './render-stub.js';

export interface ResourceFieldInput {
  name: string;
  type: string;
  options?: Record<string, unknown> | undefined;
}

export interface RenderResourceInput {
  model: string;
  fields: ResourceFieldInput[];
  namespace?: string | undefined;
  resourceName?: string | undefined;
}

export interface GeneratedFile {
  path: string;
  language: 'php';
  content: string;
}

export interface RenderResourceOutput {
  files: GeneratedFile[];
  notes: string[];
}

export type RenderResourceErrorCode =
  | 'EMPTY_MODEL'
  | 'INVALID_MODEL_IDENTIFIER'
  | 'INVALID_NAMESPACE'
  | 'INVALID_RESOURCE_NAME'
  | 'DUPLICATE_FIELD_NAME'
  | 'EMPTY_FIELD_NAME'
  | 'UNKNOWN_FIELD_TYPE';

export interface RenderResourceError {
  code: RenderResourceErrorCode;
  message: string;
  supported?: readonly string[];
}

export class RenderResourceValidationError extends Error {
  constructor(public readonly detail: RenderResourceError) {
    super(detail.message);
    this.name = 'RenderResourceValidationError';
  }
}

const PHP_IDENTIFIER = /^[A-Za-z_][A-Za-z0-9_]*$/;
const PHP_NAMESPACE = /^[A-Za-z_][A-Za-z0-9_]*(?:\\[A-Za-z_][A-Za-z0-9_]*)*$/;
const DEFAULT_NAMESPACE = 'App\\Arqel\\Resources';
const DEFAULT_MODEL_NAMESPACE = 'App\\Models';
const STUB_PATH = resolveStubPath();

function resolveStubPath(): string {
  const here = dirname(fileURLToPath(import.meta.url));
  // dist/index.js → dist/../stubs/resource.stub
  // src/scaffolding/render-resource.ts (vitest) → ../../stubs/resource.stub
  const candidates = [
    resolve(here, '..', 'stubs', 'resource.stub'),
    resolve(here, '..', '..', 'stubs', 'resource.stub'),
  ];
  for (const candidate of candidates) {
    try {
      readFileSync(candidate, 'utf-8');
      return candidate;
    } catch {
      // try next
    }
  }
  // Fall through; readFileSync will surface a clearer error at use time.
  return candidates[0]!;
}

let cachedStub: string | null = null;
function loadStub(): string {
  if (cachedStub === null) {
    cachedStub = readFileSync(STUB_PATH, 'utf-8');
  }
  return cachedStub;
}

/**
 * Render a Resource PHP file from inputs. Pure — never touches disk
 * (other than reading the bundled stub once).
 */
export function renderResource(input: RenderResourceInput): RenderResourceOutput {
  const model = input.model.trim();
  if (model === '') {
    throw new RenderResourceValidationError({
      code: 'EMPTY_MODEL',
      message: 'Model must be a non-empty string.',
    });
  }

  const { modelClass, modelFqcn } = normalizeModel(model);

  const namespace = (input.namespace ?? DEFAULT_NAMESPACE).replace(/^\\+|\\+$/g, '');
  if (!PHP_NAMESPACE.test(namespace)) {
    throw new RenderResourceValidationError({
      code: 'INVALID_NAMESPACE',
      message: `Invalid PHP namespace: "${input.namespace ?? DEFAULT_NAMESPACE}".`,
    });
  }

  const resourceName = (input.resourceName ?? `${modelClass}Resource`).trim();
  if (!PHP_IDENTIFIER.test(resourceName)) {
    throw new RenderResourceValidationError({
      code: 'INVALID_RESOURCE_NAME',
      message: `Invalid resource class name: "${resourceName}".`,
    });
  }

  // Duplicate detection
  const seenNames = new Set<string>();
  for (const field of input.fields) {
    const trimmed = field.name.trim();
    if (seenNames.has(trimmed)) {
      throw new RenderResourceValidationError({
        code: 'DUPLICATE_FIELD_NAME',
        message: `Duplicate field name: "${trimmed}".`,
      });
    }
    seenNames.add(trimmed);
  }

  // Render each field
  const aggregateNotes: string[] = [];
  const importsSet = new Set<string>();
  const fieldSnippets: string[] = [];
  for (const field of input.fields) {
    let rendered: ReturnType<typeof renderField>;
    try {
      rendered = renderField(field);
    } catch (e) {
      if (e instanceof RenderFieldValidationError) {
        throw new RenderResourceValidationError({
          code: e.detail.code,
          message: e.detail.message,
          ...(e.detail.supported ? { supported: e.detail.supported } : {}),
        });
      }
      throw e;
    }
    for (const imp of rendered.imports) importsSet.add(imp);
    for (const note of rendered.notes) aggregateNotes.push(`[${field.name}] ${note}`);
    fieldSnippets.push(indentSnippet(rendered.snippet, '            '));
  }

  const fieldsBody =
    fieldSnippets.length === 0
      ? '            // Add field definitions here.'
      : fieldSnippets.join('\n');

  const baseContent = renderStub(loadStub(), {
    namespace,
    modelClass: modelFqcn,
    class: resourceName,
    model: modelClass,
    fields: fieldsBody,
  });

  const content = injectFieldImports(baseContent, importsSet);

  const path = `app/${namespaceToPath(namespace)}/${resourceName}.php`;

  const notes = [
    "This file was generated by @arqel-dev/mcp-server. Run `php artisan arqel:install` if you haven't yet.",
    'The Resource is registered automatically via service provider discovery.',
    ...aggregateNotes,
  ];

  return {
    files: [{ path, language: 'php', content }],
    notes,
  };
}

function normalizeModel(model: string): { modelClass: string; modelFqcn: string } {
  const stripped = model.replace(/^\\+/, '');
  if (stripped.includes('\\')) {
    if (!PHP_NAMESPACE.test(stripped)) {
      throw new RenderResourceValidationError({
        code: 'INVALID_MODEL_IDENTIFIER',
        message: `Invalid model FQCN: "${model}".`,
      });
    }
    const parts = stripped.split('\\');
    const basename = parts[parts.length - 1]!;
    return { modelClass: basename, modelFqcn: stripped };
  }
  if (!PHP_IDENTIFIER.test(stripped)) {
    throw new RenderResourceValidationError({
      code: 'INVALID_MODEL_IDENTIFIER',
      message: `Invalid model identifier: "${model}".`,
    });
  }
  return { modelClass: stripped, modelFqcn: `${DEFAULT_MODEL_NAMESPACE}\\${stripped}` };
}

function injectFieldImports(content: string, imports: Set<string>): string {
  if (imports.size === 0) return content;
  const sorted = Array.from(imports).sort();
  const marker = 'use Arqel\\Core\\Resources\\Resource;';
  if (!content.includes(marker)) return content;
  return content.replace(marker, `${marker}\n${sorted.join('\n')}`);
}

function namespaceToPath(namespace: string): string {
  // App\Arqel\Resources → Arqel/Resources (drop leading "App").
  const parts = namespace.split('\\');
  if (parts[0] === 'App') parts.shift();
  return parts.join('/');
}
