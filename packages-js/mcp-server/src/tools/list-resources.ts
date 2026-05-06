import { z } from 'zod';

import {
  type IntrospectError,
  type IntrospectResource,
  introspectResources,
} from '../laravel/introspect.js';
import type { ResolvedProject } from '../laravel/resolve-project.js';
import type { ArtisanRunner } from '../laravel/run-artisan.js';

export const ListResourcesInputSchema = z.object({
  projectPath: z.string().min(1).optional(),
});

export type ListResourcesInput = z.infer<typeof ListResourcesInputSchema>;

export interface ListResourcesOkResponse {
  project: ResolvedProject;
  version: string | null;
  resources: IntrospectResource[];
}

export interface ListResourcesErrorResponse {
  project?: ResolvedProject | undefined;
  error: IntrospectError;
}

export type ListResourcesResponse = ListResourcesOkResponse | ListResourcesErrorResponse;

export interface ListResourcesOptions {
  /** Inject a fake artisan runner (tests). */
  runner?: ArtisanRunner | undefined;
}

export async function listResources(
  input: ListResourcesInput,
  options: ListResourcesOptions = {},
): Promise<ListResourcesResponse> {
  const result = await introspectResources({
    ...(input.projectPath !== undefined ? { projectPath: input.projectPath } : {}),
    ...(options.runner !== undefined ? { runner: options.runner } : {}),
  });
  if (!result.ok) {
    return { project: result.project, error: result.error };
  }
  return {
    project: result.project,
    version: result.data.version,
    resources: result.data.resources,
  };
}

const definition = {
  name: 'list_resources',
  description:
    'List all Arqel Resource classes registered in a Laravel project, by invoking `php artisan arqel:introspect`. Resolves the project from the optional `projectPath`, then `ARQEL_PROJECT_PATH`, then walks up from the current working directory.',
  inputSchema: {
    type: 'object',
    properties: {
      projectPath: {
        type: 'string',
        description: 'Absolute path to the Laravel project root. Optional — see resolution order.',
      },
    },
    required: [],
    additionalProperties: false,
  },
} as const;

export interface HandleResult {
  content: Array<{ type: 'text'; text: string }>;
  isError?: boolean;
}

function buildHandler(options: ListResourcesOptions = {}) {
  return async (rawInput: unknown): Promise<HandleResult> => {
    const parsed = ListResourcesInputSchema.safeParse(rawInput ?? {});
    if (!parsed.success) {
      return {
        content: [
          {
            type: 'text',
            text: `Invalid arguments for list_resources: ${parsed.error.message}`,
          },
        ],
        isError: true,
      };
    }
    const response = await listResources(parsed.data, options);
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

/** Factory used by tests to inject a fake artisan runner. */
export function createListResourcesTool(options: ListResourcesOptions = {}) {
  return {
    definition,
    handle: buildHandler(options),
  } as const;
}

export const listResourcesTool = {
  definition,
  handle(rawInput: unknown): Promise<HandleResult> {
    return buildHandler()(rawInput);
  },
} as const;
