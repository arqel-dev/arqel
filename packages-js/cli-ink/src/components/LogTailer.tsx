import { existsSync, readFileSync } from 'node:fs';
import { Box, Text } from 'ink';
import type { ReactElement } from 'react';
import { useEffect, useState } from 'react';

export type LogTailerProps = {
  filePath: string;
  follow?: boolean;
  /** Maximum lines to keep in memory / display. */
  maxLines?: number;
  pollMs?: number;
  /** Test seam — defaults to fs.readFileSync. */
  readFile?: (path: string) => string;
  fileExists?: (path: string) => boolean;
};

export type LogLevel = 'ERROR' | 'WARN' | 'INFO' | 'DEBUG' | 'OTHER';

export function classifyLine(line: string): LogLevel {
  const upper = line.toUpperCase();
  if (upper.includes('ERROR') || upper.includes('FATAL')) return 'ERROR';
  if (upper.includes('WARN')) return 'WARN';
  if (upper.includes('INFO')) return 'INFO';
  if (upper.includes('DEBUG')) return 'DEBUG';
  return 'OTHER';
}

const LEVEL_COLORS: Record<LogLevel, string | undefined> = {
  ERROR: 'red',
  WARN: 'yellow',
  INFO: 'cyan',
  DEBUG: 'gray',
  OTHER: undefined,
};

export function LogTailer({
  filePath,
  follow = false,
  maxLines = 200,
  pollMs = 500,
  readFile = (p) => readFileSync(p, 'utf8'),
  fileExists = (p) => existsSync(p),
}: LogTailerProps): ReactElement {
  const [lines, setLines] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    const tick = (): void => {
      if (cancelled) return;
      try {
        if (!fileExists(filePath)) {
          setError(`File not found: ${filePath}`);
          return;
        }
        const raw = readFile(filePath);
        const allLines = raw.split(/\r?\n/).filter((l) => l.length > 0);
        const tail = allLines.slice(-maxLines);
        setLines(tail);
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err.message : String(err));
      }
    };

    tick();
    if (!follow) {
      return () => {
        cancelled = true;
      };
    }
    const handle = setInterval(tick, pollMs);
    return () => {
      cancelled = true;
      clearInterval(handle);
    };
  }, [filePath, follow, pollMs, maxLines, readFile, fileExists]);

  if (error) {
    return <Text color="red">Error: {error}</Text>;
  }

  return (
    <Box flexDirection="column">
      <Text bold color="cyan">
        Log: {filePath} {follow ? '(following)' : ''}
      </Text>
      {lines.map((line) => {
        const level = classifyLine(line);
        const color = LEVEL_COLORS[level];
        return (
          <Text key={line} {...(color ? { color } : {})}>
            {line}
          </Text>
        );
      })}
    </Box>
  );
}
