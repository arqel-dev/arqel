import { z } from 'zod';

import {
  type IntrospectError,
  type IntrospectResource,
  introspectResources,
  normalizeFqcn,
} from '../laravel/introspect.js';
import type { ResolvedProject } from '../laravel/resolve-project.js';
import type { ArtisanRunner } from '../laravel/run-artisan.js';

const MAX_AVAILABLE = 50;

export const DescribeResourceInputSchema = z.object({
  class: z.string().min(1, 'class must be a non-empty string'),
  projectPath: z.string().min(1).optional(),
});

export type DescribeResourceInput = z.infer<typeof DescribeResourceInputSchema>;

export interface DescribeResourceFoundResponse {
  project: ResolvedProject;
  version: string | null;
  resource: IntrospectResource;
}

export interface DescribeResourceMissingResponse {
  project: ResolvedProject;
  match: 'none';
  available: string[];
}

export interface DescribeResourceErrorResponse {
  project?: ResolvedProject | undefined;
  error: IntrospectError;
}

export type DescribeResourceResponse =
  | DescribeResourceFoundResponse
  | DescribeResourceMissingResponse
  | DescribeResourceErrorResponse;

export interface DescribeResourceOptions {
  runner?: ArtisanRunner | undefined;
}

export async function describeResource(
  input: DescribeResourceInput,
  options: DescribeResourceOptions = {},
): Promise<DescribeResourceResponse> {
  const result = await introspectResources({
    ...(input.projectPath !== undefined ? { projectPath: input.projectPath } : {}),
    ...(options.runner !== undefined ? { runner: options.runner } : {}),
  });
  if (!result.ok) {
    return { project: result.project, error: result.error };
  }
  const target = normalizeFqcn(input.class);
  const match = result.data.resources.find((resource) => normalizeFqcn(resource.class) === target);
  if (!match) {
    return {
      project: result.project,
      match: 'none',
      available: result.data.resources.slice(0, MAX_AVAILABLE).map((r) => r.class),
    };
  }
  return {
    project: result.project,
    version: result.data.version,
    resource: match,
  };
}

const definition = {
  name: 'describe_resource',
  description:
    'Describe a single Arqel Resource (fields, model, slug, policies) by its FQCN. Accepts both `App\\\\Resources\\\\PostResource` (escaped) and `App\\Resources\\PostResource` (raw). Resolution order for the project: `projectPath` param, `ARQEL_PROJECT_PATH` env var, walk up from cwd.',
  inputSchema: {
    type: 'object',
    properties: {
      class: {
        type: 'string',
        description:
          'Fully-qualified class name of the Arqel Resource. Backslashes may be escaped or not.',
      },
      projectPath: {
        type: 'string',
        description: 'Absolute path to the Laravel project root. Optional — see description.',
      },
    },
    required: ['class'],
    additionalProperties: false,
  },
} as const;

export interface HandleResult {
  content: Array<{ type: 'text'; text: string }>;
  isError?: boolean;
}

function buildHandler(options: DescribeResourceOptions = {}) {
  return async (rawInput: unknown): Promise<HandleResult> => {
    const parsed = DescribeResourceInputSchema.safeParse(rawInput);
    if (!parsed.success) {
      return {
        content: [
          {
            type: 'text',
            text: `Invalid arguments for describe_resource: ${parsed.error.message}`,
          },
        ],
        isError: true,
      };
    }
    const response = await describeResource(parsed.data, options);
    const isError = 'error' in response;
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify(response, null, 2),
        },
      ],
      ...(isError ? { isError: true } : {}),
    };
  };
}

export function createDescribeResourceTool(options: DescribeResourceOptions = {}) {
  return {
    definition,
    handle: buildHandler(options),
  } as const;
}

export const describeResourceTool = {
  definition,
  handle(rawInput: unknown): Promise<HandleResult> {
    return buildHandler()(rawInput);
  },
} as const;
