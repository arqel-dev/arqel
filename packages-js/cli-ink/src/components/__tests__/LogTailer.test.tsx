import { render } from 'ink-testing-library';
import { describe, expect, it } from 'vitest';
import { classifyLine, LogTailer } from '../LogTailer.js';

const sample = [
  '2026-04-30 12:00:01 INFO Application booted',
  '2026-04-30 12:00:02 DEBUG Cache warmup',
  '2026-04-30 12:00:03 WARN Slow query',
  '2026-04-30 12:00:04 ERROR Failed to send notification',
].join('\n');

describe('classifyLine', () => {
  it('classifies known levels', () => {
    expect(classifyLine('foo ERROR bar')).toBe('ERROR');
    expect(classifyLine('foo WARN bar')).toBe('WARN');
    expect(classifyLine('foo INFO bar')).toBe('INFO');
    expect(classifyLine('foo DEBUG bar')).toBe('DEBUG');
    expect(classifyLine('plain line')).toBe('OTHER');
  });
});

describe('LogTailer', () => {
  it('renders all log lines from the file', async () => {
    const { lastFrame, unmount } = render(
      <LogTailer
        filePath="/fake.log"
        readFile={() => sample}
        fileExists={() => true}
        follow={false}
      />,
    );
    await new Promise((r) => setTimeout(r, 10));
    const frame = lastFrame() ?? '';
    expect(frame).toContain('Application booted');
    expect(frame).toContain('Cache warmup');
    expect(frame).toContain('Slow query');
    expect(frame).toContain('Failed to send notification');
    unmount();
  });

  it('caps the rendered lines at maxLines', async () => {
    const many = Array.from({ length: 50 }, (_, i) => `line ${i}`).join('\n');
    const { lastFrame, unmount } = render(
      <LogTailer
        filePath="/fake.log"
        readFile={() => many}
        fileExists={() => true}
        maxLines={5}
        follow={false}
      />,
    );
    await new Promise((r) => setTimeout(r, 10));
    const frame = lastFrame() ?? '';
    expect(frame).toContain('line 49');
    expect(frame).toContain('line 45');
    expect(frame).not.toContain('line 0\n');
    unmount();
  });

  it('renders an error when file is missing', async () => {
    const { lastFrame, unmount } = render(
      <LogTailer filePath="/missing" readFile={() => ''} fileExists={() => false} />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(lastFrame() ?? '').toMatch(/Error:.*not found/);
    unmount();
  });

  it('re-reads file when follow=true', async () => {
    let n = 0;
    const reads: string[] = [];
    const { unmount } = render(
      <LogTailer
        filePath="/f.log"
        readFile={() => {
          const v = `line ${n++}`;
          reads.push(v);
          return v;
        }}
        fileExists={() => true}
        follow={true}
        pollMs={20}
      />,
    );
    await new Promise((r) => setTimeout(r, 80));
    unmount();
    expect(reads.length).toBeGreaterThan(1);
  });
});
