import { z } from 'zod';

import {
  type RenderFieldOutput,
  RenderFieldValidationError,
  renderField,
} from '../scaffolding/render-field.js';

export const GenerateFieldInputSchema = z.object({
  name: z.string().min(1, 'name must be a non-empty string'),
  type: z.string().min(1, 'type must be a non-empty string'),
  options: z.record(z.string(), z.unknown()).optional(),
});

export type GenerateFieldInput = z.infer<typeof GenerateFieldInputSchema>;

export interface GenerateFieldOkResponse extends RenderFieldOutput {}

export interface GenerateFieldErrorResponse {
  error: {
    code: 'INVALID_INPUT' | 'EMPTY_FIELD_NAME' | 'UNKNOWN_FIELD_TYPE';
    message: string;
    supported?: readonly string[];
  };
}

export type GenerateFieldResponse = GenerateFieldOkResponse | GenerateFieldErrorResponse;

export function generateField(input: GenerateFieldInput): GenerateFieldResponse {
  try {
    return renderField(input);
  } catch (e) {
    if (e instanceof RenderFieldValidationError) {
      return {
        error: {
          code: e.detail.code,
          message: e.detail.message,
          ...(e.detail.supported ? { supported: e.detail.supported } : {}),
        },
      };
    }
    throw e;
  }
}

const definition = {
  name: 'generate_field',
  description:
    'Generate a single Arqel Field declaration as PHP source. Returns a snippet ready to drop into a Resource `fields()` array, plus the `use` statement(s) to add. Pure code generation — never writes to disk.',
  inputSchema: {
    type: 'object',
    properties: {
      name: {
        type: 'string',
        description: 'Field name (snake_case or camelCase).',
      },
      type: {
        type: 'string',
        description:
          'Field type. Case-insensitive. Supported: Text, Textarea, Number, Currency, Boolean, Toggle, Select, MultiSelect, Radio, Email, URL, Password, Slug, Date, DateTime, BelongsTo, HasMany, File, Image, Color, Hidden.',
      },
      options: {
        type: 'object',
        description:
          'Known keys: required, nullable, default, placeholder, helpText, options. Unknown keys appear in the response notes.',
        additionalProperties: true,
      },
    },
    required: ['name', 'type'],
    additionalProperties: false,
  },
} as const;

export interface HandleResult {
  content: Array<{ type: 'text'; text: string }>;
  isError?: boolean;
}

export const generateFieldTool = {
  definition,
  async handle(rawInput: unknown): Promise<HandleResult> {
    const parsed = GenerateFieldInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      const body: GenerateFieldErrorResponse = {
        error: { code: 'INVALID_INPUT', message: parsed.error.message },
      };
      return {
        content: [{ type: 'text', text: JSON.stringify(body, null, 2) }],
        isError: true,
      };
    }
    const response = generateField(parsed.data);
    const isError = 'error' in response;
    return {
      content: [{ type: 'text', text: JSON.stringify(response, null, 2) }],
      ...(isError ? { isError: true } : {}),
    };
  },
} as const;
