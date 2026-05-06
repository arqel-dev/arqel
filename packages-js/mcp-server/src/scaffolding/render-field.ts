import { type FieldTypeSpec, resolveFieldType, SUPPORTED_FIELD_TYPE_NAMES } from './field-types.js';

export interface RenderFieldInput {
  name: string;
  type: string;
  options?: Record<string, unknown> | undefined;
}

export interface RenderFieldOutput {
  snippet: string;
  imports: string[];
  notes: string[];
}

export interface RenderFieldError {
  code: 'EMPTY_FIELD_NAME' | 'UNKNOWN_FIELD_TYPE';
  message: string;
  supported?: readonly string[];
}

export class RenderFieldValidationError extends Error {
  constructor(public readonly detail: RenderFieldError) {
    super(detail.message);
    this.name = 'RenderFieldValidationError';
  }
}

const KNOWN_OPTION_KEYS = new Set([
  'required',
  'nullable',
  'default',
  'placeholder',
  'helpText',
  'options',
]);

/**
 * Render a single Field declaration as it would appear inside a Resource's
 * `fields()` array. Pure — no I/O, no PHP side effects.
 */
export function renderField(input: RenderFieldInput): RenderFieldOutput {
  const name = input.name.trim();
  if (name === '') {
    throw new RenderFieldValidationError({
      code: 'EMPTY_FIELD_NAME',
      message: 'Field name must be a non-empty string.',
    });
  }

  const spec = resolveFieldType(input.type);
  if (!spec) {
    throw new RenderFieldValidationError({
      code: 'UNKNOWN_FIELD_TYPE',
      message: `Unknown field type "${input.type}". Supported: ${SUPPORTED_FIELD_TYPE_NAMES.join(', ')}.`,
      supported: SUPPORTED_FIELD_TYPE_NAMES,
    });
  }

  const notes: string[] = [];
  const calls = renderOptionCalls(input.options ?? {}, notes);

  const factory = `${spec.className}::make('${escapeSingle(name)}')`;
  const snippet = calls.length === 0 ? `${factory},` : `${factory}\n${calls.join('\n')},`;

  const imports = [`use ${spec.fqcn};`];

  return { snippet, imports, notes };
}

/** Render the snippet indented for placement inside a `fields()` array body. */
export function indentSnippet(snippet: string, indent: string): string {
  return snippet
    .split('\n')
    .map((line) => (line === '' ? '' : indent + line))
    .join('\n');
}

function renderOptionCalls(options: Record<string, unknown>, notes: string[]): string[] {
  const calls: string[] = [];
  const indent = '    ';

  for (const [key, value] of Object.entries(options)) {
    if (!KNOWN_OPTION_KEYS.has(key)) {
      notes.push(`Option \`${key}\` is not recognized; pass it manually.`);
      continue;
    }
    const rendered = renderSingleOption(key, value);
    if (rendered === null) {
      notes.push(`Option \`${key}\` had an unsupported value type; pass it manually.`);
      continue;
    }
    calls.push(`${indent}${rendered}`);
  }

  return calls;
}

function renderSingleOption(key: string, value: unknown): string | null {
  switch (key) {
    case 'required':
      return value === true ? '->required()' : null;
    case 'nullable':
      return value === true ? '->nullable()' : null;
    case 'default':
      return `->default(${renderPhpScalar(value)})`;
    case 'placeholder':
      if (typeof value !== 'string') return null;
      return `->placeholder('${escapeSingle(value)}')`;
    case 'helpText':
      if (typeof value !== 'string') return null;
      return `->helpText('${escapeSingle(value)}')`;
    case 'options': {
      if (!isStringRecord(value)) return null;
      return `->options(${renderPhpAssocArray(value)})`;
    }
    default:
      return null;
  }
}

function renderPhpScalar(value: unknown): string {
  if (value === null) return 'null';
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  if (typeof value === 'string') return `'${escapeSingle(value)}'`;
  // Fallback to JSON-ish for anything else; the caller will see a note.
  return `'${escapeSingle(JSON.stringify(value))}'`;
}

function renderPhpAssocArray(record: Record<string, string>): string {
  const entries = Object.entries(record).map(
    ([k, v]) => `'${escapeSingle(k)}' => '${escapeSingle(v)}'`,
  );
  return `[${entries.join(', ')}]`;
}

function isStringRecord(value: unknown): value is Record<string, string> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return false;
  return Object.values(value as Record<string, unknown>).every((v) => typeof v === 'string');
}

function escapeSingle(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

export type { FieldTypeSpec };
