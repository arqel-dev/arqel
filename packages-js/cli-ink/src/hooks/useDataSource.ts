import { existsSync, readFileSync } from 'node:fs';
import { useEffect, useRef, useState } from 'react';

export type DataSourceState<T> = {
  data: T | null;
  loading: boolean;
  error: string | null;
};

export type UseDataSourceOptions = {
  /** Polling interval in milliseconds. Set to 0 to disable polling (one-shot read). */
  pollMs?: number;
  /** Inject a custom reader for testability. Defaults to fs.readFileSync. */
  readFile?: (path: string) => string;
  /** Inject a custom existence check. Defaults to fs.existsSync. */
  fileExists?: (path: string) => boolean;
};

const defaultReadFile = (path: string): string => readFileSync(path, 'utf8');
const defaultFileExists = (path: string): boolean => existsSync(path);

/**
 * Reads a JSON file from disk and optionally polls for updates.
 *
 * Designed for Ink-based TUIs that consume Arqel "data manifests" produced by
 * an authoritative tool (e.g. `arqel:cli:export-data`) rather than connecting
 * directly to a running Laravel app.
 */
export function useDataSource<T = unknown>(
  filePath: string,
  options: UseDataSourceOptions = {},
): DataSourceState<T> {
  const { pollMs = 1000, readFile = defaultReadFile, fileExists = defaultFileExists } = options;
  const [state, setState] = useState<DataSourceState<T>>({
    data: null,
    loading: true,
    error: null,
  });
  const lastPayload = useRef<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    const tick = (): void => {
      if (cancelled) return;
      try {
        if (!fileExists(filePath)) {
          setState({ data: null, loading: false, error: `File not found: ${filePath}` });
          return;
        }
        const raw = readFile(filePath);
        if (raw === lastPayload.current) return;
        lastPayload.current = raw;
        const parsed = JSON.parse(raw) as T;
        setState({ data: parsed, loading: false, error: null });
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        setState({ data: null, loading: false, error: message });
      }
    };

    tick();

    if (pollMs <= 0) {
      return () => {
        cancelled = true;
      };
    }

    const handle = setInterval(tick, pollMs);
    return () => {
      cancelled = true;
      clearInterval(handle);
    };
  }, [filePath, pollMs, readFile, fileExists]);

  return state;
}
