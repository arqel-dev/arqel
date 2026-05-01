import { Text } from 'ink';
import { render } from 'ink-testing-library';
import type { ReactElement } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { useDataSource } from '../useDataSource.js';

function Probe({
  path,
  readFile,
  fileExists,
  pollMs = 0,
}: {
  path: string;
  readFile?: (p: string) => string;
  fileExists?: (p: string) => boolean;
  pollMs?: number;
}): ReactElement {
  const opts: Parameters<typeof useDataSource>[1] = { pollMs };
  if (readFile) opts.readFile = readFile;
  if (fileExists) opts.fileExists = fileExists;
  const state = useDataSource<{ value: number }>(path, opts);
  if (state.loading) return <Text>loading</Text>;
  if (state.error) return <Text>err:{state.error}</Text>;
  return <Text>val:{state.data?.value ?? -1}</Text>;
}

describe('useDataSource', () => {
  it('reads happy path JSON via injected fs', async () => {
    const readFile = vi.fn().mockReturnValue('{"value": 42}');
    const fileExists = vi.fn().mockReturnValue(true);
    const { lastFrame, unmount } = render(
      <Probe path="/x.json" readFile={readFile} fileExists={fileExists} />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(lastFrame()).toContain('val:42');
    unmount();
  });

  it('returns error state when file is missing', async () => {
    const readFile = vi.fn();
    const fileExists = vi.fn().mockReturnValue(false);
    const { lastFrame, unmount } = render(
      <Probe path="/missing.json" readFile={readFile} fileExists={fileExists} />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(lastFrame()).toMatch(/err:File not found/);
    expect(readFile).not.toHaveBeenCalled();
    unmount();
  });

  it('returns error state when JSON is malformed', async () => {
    const readFile = vi.fn().mockReturnValue('not-json');
    const fileExists = vi.fn().mockReturnValue(true);
    const { lastFrame, unmount } = render(
      <Probe path="/bad.json" readFile={readFile} fileExists={fileExists} />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(lastFrame()).toMatch(/err:/);
    unmount();
  });

  it('polling re-reads file at the configured interval', async () => {
    let n = 1;
    const readFile = vi.fn(() => `{"value": ${n++}}`);
    const fileExists = vi.fn().mockReturnValue(true);
    const { lastFrame, unmount } = render(
      <Probe path="/p.json" readFile={readFile} fileExists={fileExists} pollMs={20} />,
    );
    await new Promise((r) => setTimeout(r, 100));
    expect(readFile.mock.calls.length).toBeGreaterThan(1);
    expect(lastFrame()).toMatch(/val:/);
    unmount();
  });

  it('stops polling on unmount (cleanup)', async () => {
    const readFile = vi.fn().mockReturnValue('{"value": 7}');
    const fileExists = vi.fn().mockReturnValue(true);
    const { unmount } = render(
      <Probe path="/c.json" readFile={readFile} fileExists={fileExists} pollMs={20} />,
    );
    await new Promise((r) => setTimeout(r, 50));
    const callsBefore = readFile.mock.calls.length;
    unmount();
    await new Promise((r) => setTimeout(r, 60));
    const callsAfter = readFile.mock.calls.length;
    expect(callsAfter).toBe(callsBefore);
  });
});
