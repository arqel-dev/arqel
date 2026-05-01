import { render } from 'ink-testing-library';
import { describe, expect, it, vi } from 'vitest';
import { MainMenu } from '../MainMenu.js';

// ink-testing-library forwards raw bytes to useInput. Use vim-style j/k since
// arrow key escape sequences vary between terminals; useNavigableList accepts both.
const DOWN = 'j';
const UP = 'k';
const ENTER = '\r';

describe('MainMenu', () => {
  it('renders all four options with first highlighted', () => {
    const { lastFrame, unmount } = render(<MainMenu />);
    const frame = lastFrame() ?? '';
    expect(frame).toContain('Dashboard');
    expect(frame).toContain('Resources');
    expect(frame).toContain('Logs');
    expect(frame).toContain('Quit');
    expect(frame).toMatch(/>\s*Dashboard/);
    unmount();
  });

  it('moves selection down on j', async () => {
    const { lastFrame, stdin, unmount } = render(<MainMenu />);
    stdin.write(DOWN);
    await new Promise((r) => setTimeout(r, 20));
    expect(lastFrame() ?? '').toMatch(/>\s*Resources/);
    unmount();
  });

  it('wraps on k from first item', async () => {
    const { lastFrame, stdin, unmount } = render(<MainMenu />);
    stdin.write(UP);
    await new Promise((r) => setTimeout(r, 20));
    expect(lastFrame() ?? '').toMatch(/>\s*Quit/);
    unmount();
  });

  it('dispatches onSelect on Enter', async () => {
    const onSelect = vi.fn();
    const { stdin, unmount } = render(<MainMenu onSelect={onSelect} />);
    stdin.write(ENTER);
    await new Promise((r) => setTimeout(r, 20));
    expect(onSelect).toHaveBeenCalledWith('dashboard');
    unmount();
  });
});
