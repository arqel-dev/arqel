import { render } from 'ink-testing-library';
import { describe, expect, it, vi } from 'vitest';
import { Dashboard } from '../Dashboard.js';

const sample = JSON.stringify({
  queriesPerSec: 145,
  activeUsers: 23,
  errors: 2,
  aiTokens: 8400,
});

describe('Dashboard', () => {
  it('renders the four tile labels when data is loaded', async () => {
    const { lastFrame, unmount } = render(
      <Dashboard
        dataDir="/fake"
        pollMs={0}
        ioOverrides={{ readFile: () => sample, fileExists: () => true }}
      />,
    );
    await new Promise((r) => setTimeout(r, 10));
    const frame = lastFrame() ?? '';
    expect(frame).toContain('Queries / sec');
    expect(frame).toContain('Active users');
    expect(frame).toContain('Errors');
    expect(frame).toContain('AI tokens');
    unmount();
  });

  it('renders the values from the JSON payload', async () => {
    const { lastFrame, unmount } = render(
      <Dashboard
        dataDir="/fake"
        pollMs={0}
        ioOverrides={{ readFile: () => sample, fileExists: () => true }}
      />,
    );
    await new Promise((r) => setTimeout(r, 10));
    const frame = lastFrame() ?? '';
    expect(frame).toContain('145');
    expect(frame).toContain('23');
    expect(frame).toContain('8400');
    unmount();
  });

  it('renders an error message when the data file is missing', async () => {
    const { lastFrame, unmount } = render(
      <Dashboard
        dataDir="/missing"
        pollMs={0}
        ioOverrides={{ readFile: () => '', fileExists: () => false }}
      />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(lastFrame() ?? '').toMatch(/Error:.*not found/);
    unmount();
  });

  it('reads from the configured dataDir', async () => {
    const readFile = vi.fn().mockReturnValue(sample);
    const fileExists = vi.fn().mockReturnValue(true);
    const { unmount } = render(
      <Dashboard dataDir="/custom" pollMs={0} ioOverrides={{ readFile, fileExists }} />,
    );
    await new Promise((r) => setTimeout(r, 10));
    expect(fileExists).toHaveBeenCalled();
    const calledPath = fileExists.mock.calls[0]?.[0] as string;
    expect(calledPath).toContain('/custom');
    expect(calledPath).toContain('dashboard.json');
    unmount();
  });
});
