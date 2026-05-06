import { z } from 'zod';

import {
  type RenderResourceOutput,
  RenderResourceValidationError,
  renderResource,
} from '../scaffolding/render-resource.js';

export const GenerateResourceInputSchema = z.object({
  model: z.string().min(1, 'model must be a non-empty string'),
  fields: z
    .array(
      z.object({
        name: z.string().min(1),
        type: z.string().min(1),
        options: z.record(z.string(), z.unknown()).optional(),
      }),
    )
    .default([]),
  namespace: z.string().min(1).optional(),
  resourceName: z.string().min(1).optional(),
});

export type GenerateResourceInput = z.infer<typeof GenerateResourceInputSchema>;

export interface GenerateResourceOkResponse extends RenderResourceOutput {}

export interface GenerateResourceErrorResponse {
  error: {
    code:
      | 'INVALID_INPUT'
      | 'EMPTY_MODEL'
      | 'INVALID_MODEL_IDENTIFIER'
      | 'INVALID_NAMESPACE'
      | 'INVALID_RESOURCE_NAME'
      | 'DUPLICATE_FIELD_NAME'
      | 'EMPTY_FIELD_NAME'
      | 'UNKNOWN_FIELD_TYPE';
    message: string;
    supported?: readonly string[];
  };
}

export type GenerateResourceResponse = GenerateResourceOkResponse | GenerateResourceErrorResponse;

export function generateResource(input: GenerateResourceInput): GenerateResourceResponse {
  try {
    return renderResource(input);
  } catch (e) {
    if (e instanceof RenderResourceValidationError) {
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
  name: 'generate_resource',
  description:
    'Generate an Arqel Resource PHP class as source code. Returns the generated file (path + content), without touching disk. The path is relative to the Laravel project root.',
  inputSchema: {
    type: 'object',
    properties: {
      model: {
        type: 'string',
        description:
          'Model class basename (e.g. "Post") or FQCN (e.g. "App\\\\Models\\\\Post"). When only a basename is given, "App\\\\Models\\\\" is prepended.',
      },
      fields: {
        type: 'array',
        description: 'List of fields to scaffold inside the Resource fields() method.',
        items: {
          type: 'object',
          properties: {
            name: { type: 'string' },
            type: { type: 'string' },
            options: { type: 'object', additionalProperties: true },
          },
          required: ['name', 'type'],
          additionalProperties: false,
        },
      },
      namespace: {
        type: 'string',
        description: 'PHP namespace for the Resource. Defaults to "App\\\\Arqel\\\\Resources".',
      },
      resourceName: {
        type: 'string',
        description: 'Override the generated resource class name. Defaults to "<Model>Resource".',
      },
    },
    required: ['model'],
    additionalProperties: false,
  },
} as const;

export interface HandleResult {
  content: Array<{ type: 'text'; text: string }>;
  isError?: boolean;
}

export const generateResourceTool = {
  definition,
  async handle(rawInput: unknown): Promise<HandleResult> {
    const parsed = GenerateResourceInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      const body: GenerateResourceErrorResponse = {
        error: { code: 'INVALID_INPUT', message: parsed.error.message },
      };
      return {
        content: [{ type: 'text', text: JSON.stringify(body, null, 2) }],
        isError: true,
      };
    }
    const response = generateResource(parsed.data);
    const isError = 'error' in response;
    return {
      content: [{ type: 'text', text: JSON.stringify(response, null, 2) }],
      ...(isError ? { isError: true } : {}),
    };
  },
} as const;
