import { render } from 'ink-testing-library';
import { describe, expect, it } from 'vitest';
import { ResourceBrowser } from '../ResourceBrowser.js';

const payload = JSON.stringify([
  { slug: 'users', label: 'Users', count: 1240, description: 'App users.' },
  { slug: 'posts', label: 'Posts', count: 580, description: 'Blog posts.' },
  { slug: 'tags', label: 'Tags', count: 92 },
]);

const io = { readFile: () => payload, fileExists: () => true };

describe('ResourceBrowser', () => {
  it('renders all resources in the left pane', async () => {
    const { lastFrame, unmount } = render(
      <ResourceBrowser dataDir="/fake" pollMs={0} ioOverrides={io} />,
    );
    await new Promise((r) => setTimeout(r, 200));
    const frame = lastFrame() ?? '';
    expect(frame).toContain('Users');
    expect(frame).toContain('Posts');
    expect(frame).toContain('Tags');
    unmount();
  });

  it('shows detail for the first resource by default', async () => {
    const { lastFrame, unmount } = render(
      <ResourceBrowser dataDir="/fake" pollMs={0} ioOverrides={io} />,
    );
    await new Promise((r) => setTimeout(r, 200));
    const frame = lastFrame() ?? '';
    expect(frame).toMatch(/slug:\s*users/);
    expect(frame).toContain('App users.');
    unmount();
  });

  it('navigates to the next resource on j', async () => {
    const { lastFrame, stdin, unmount } = render(
      <ResourceBrowser dataDir="/fake" pollMs={0} ioOverrides={io} />,
    );
    await new Promise((r) => setTimeout(r, 200));
    stdin.write('j');
    await new Promise((r) => setTimeout(r, 50));
    const frame = lastFrame() ?? '';
    expect(frame).toMatch(/slug:\s*posts/);
    unmount();
  });

  it('groups record counts with the period separator under a pt_BR locale', async () => {
    const saved = process.env['LANG'];
    process.env['LANG'] = 'pt_BR.UTF-8';
    try {
      const bigPayload = JSON.stringify([
        { slug: 'users', label: 'Users', count: 12345, description: 'App users.' },
      ]);
      const { lastFrame, unmount } = render(
        <ResourceBrowser
          dataDir="/fake"
          pollMs={0}
          ioOverrides={{ readFile: () => bigPayload, fileExists: () => true }}
        />,
      );
      await new Promise((r) => setTimeout(r, 200));
      const frame = lastFrame() ?? '';
      // Sidebar count and detail-pane "Records:" both grouped.
      expect(frame).toContain('(12.345)');
      expect(frame).toContain('12.345');
      expect(frame).not.toContain('12345');
      unmount();
    } finally {
      if (saved === undefined) delete process.env['LANG'];
      else process.env['LANG'] = saved;
    }
  });

  it('renders an error when resources file is missing', async () => {
    const { lastFrame, unmount } = render(
      <ResourceBrowser
        dataDir="/x"
        pollMs={0}
        ioOverrides={{ readFile: () => '', fileExists: () => false }}
      />,
    );
    await new Promise((r) => setTimeout(r, 200));
    expect(lastFrame() ?? '').toMatch(/Error:.*not found/);
    unmount();
  });
});
