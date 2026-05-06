import { z } from 'zod';

import { type ResolvedProject, resolveLaravelProject } from './resolve-project.js';
import {
  type ArtisanRunner,
  ArtisanSpawnError,
  ArtisanTimeoutError,
  runArtisan,
} from './run-artisan.js';

const STDOUT_TRUNCATE = 2 * 1024;
const STDERR_TRUNCATE = 2 * 1024;

const FieldSchema = z.object({
  name: z.string(),
  type: z.string(),
});

const ResourceSchema = z.object({
  class: z.string(),
  model: z.string().nullable(),
  label: z.string().nullable(),
  pluralLabel: z.string().nullable(),
  slug: z.string().nullable(),
  fields: z.array(FieldSchema),
  policies: z.array(z.string()),
});

export type IntrospectField = z.infer<typeof FieldSchema>;
export type IntrospectResource = z.infer<typeof ResourceSchema>;

export const IntrospectPayloadSchema = z.object({
  version: z.string().nullable(),
  scope: z.enum(['all', 'panels', 'resources', 'fields']),
  resources: z.array(ResourceSchema),
  panels: z.array(z.unknown()).optional(),
  fields: z.array(z.unknown()).optional(),
});

export type IntrospectPayload = z.infer<typeof IntrospectPayloadSchema>;

export type IntrospectErrorCode =
  | 'PROJECT_NOT_FOUND'
  | 'ARTISAN_FAILED'
  | 'ARTISAN_TIMEOUT'
  | 'ARTISAN_SPAWN_FAILED'
  | 'INVALID_JSON_OUTPUT'
  | 'UNEXPECTED_OUTPUT';

export interface IntrospectSimpleError {
  code: 'PROJECT_NOT_FOUND' | 'ARTISAN_TIMEOUT' | 'ARTISAN_SPAWN_FAILED';
  message: string;
}

export interface IntrospectArtisanFailedError {
  code: 'ARTISAN_FAILED';
  message: string;
  exitCode: number;
  stderr: string;
}

export interface IntrospectInvalidJsonError {
  code: 'INVALID_JSON_OUTPUT';
  message: string;
  stdout: string;
}

export interface IntrospectUnexpectedOutputError {
  code: 'UNEXPECTED_OUTPUT';
  message: string;
  issues: string[];
}

export type IntrospectError =
  | IntrospectSimpleError
  | IntrospectArtisanFailedError
  | IntrospectInvalidJsonError
  | IntrospectUnexpectedOutputError;

export type IntrospectResult =
  | { ok: true; data: IntrospectPayload; project: ResolvedProject }
  | { ok: false; error: IntrospectError; project?: ResolvedProject | undefined };

export interface IntrospectResourcesOptions {
  projectPath?: string | undefined;
  /** Inject a fake runner for tests. Defaults to the real `runArtisan`. */
  runner?: ArtisanRunner | undefined;
}

function truncate(text: string, max: number): string {
  if (text.length <= max) return text;
  return `${text.slice(0, max)}…[truncated]`;
}

/**
 * Resolve the project, run `php artisan arqel:introspect --json --scope=resources`,
 * parse + validate the output. Never throws — errors are returned as values.
 */
export async function introspectResources(
  options: IntrospectResourcesOptions = {},
): Promise<IntrospectResult> {
  const project = await resolveLaravelProject(
    options.projectPath !== undefined ? { projectPath: options.projectPath } : {},
  );
  if (!project) {
    return {
      ok: false,
      error: {
        code: 'PROJECT_NOT_FOUND',
        message:
          'Laravel project not found. Provide `projectPath`, set ARQEL_PROJECT_PATH, or run from inside a Laravel project (containing an `artisan` file).',
      },
    };
  }

  const runner: ArtisanRunner = options.runner ?? runArtisan;

  let result: Awaited<ReturnType<ArtisanRunner>>;
  try {
    result = await runner(project.root, ['arqel:introspect', '--json', '--scope=resources']);
  } catch (err) {
    if (err instanceof ArtisanTimeoutError) {
      return {
        ok: false,
        project,
        error: { code: 'ARTISAN_TIMEOUT', message: err.message },
      };
    }
    if (err instanceof ArtisanSpawnError) {
      return {
        ok: false,
        project,
        error: { code: 'ARTISAN_SPAWN_FAILED', message: err.message },
      };
    }
    const message = err instanceof Error ? err.message : String(err);
    return {
      ok: false,
      project,
      error: { code: 'ARTISAN_SPAWN_FAILED', message },
    };
  }

  if (result.exitCode !== 0) {
    return {
      ok: false,
      project,
      error: {
        code: 'ARTISAN_FAILED',
        message: `php artisan exited with code ${result.exitCode}`,
        exitCode: result.exitCode,
        stderr: truncate(result.stderr, STDERR_TRUNCATE),
      } satisfies IntrospectArtisanFailedError,
    };
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(result.stdout);
  } catch {
    return {
      ok: false,
      project,
      error: {
        code: 'INVALID_JSON_OUTPUT',
        message: 'artisan output was not valid JSON',
        stdout: truncate(result.stdout, STDOUT_TRUNCATE),
      } satisfies IntrospectInvalidJsonError,
    };
  }

  const validated = IntrospectPayloadSchema.safeParse(parsed);
  if (!validated.success) {
    return {
      ok: false,
      project,
      error: {
        code: 'UNEXPECTED_OUTPUT',
        message: 'artisan output did not match the expected introspection schema',
        issues: validated.error.issues.map(
          (issue) => `${issue.path.join('.') || '<root>'}: ${issue.message}`,
        ),
      } satisfies IntrospectUnexpectedOutputError,
    };
  }

  return { ok: true, data: validated.data, project };
}

/** Normalize a PHP FQCN: collapse runs of backslashes to a single backslash and strip a leading `\`. */
export function normalizeFqcn(input: string): string {
  return input.replace(/\\+/g, '\\').replace(/^\\/, '');
}
