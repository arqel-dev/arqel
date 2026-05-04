import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { useFocusTrap } from '../useFocusTrap';

function TrapFixture({
  active,
  onEscape,
}: {
  active: boolean;
  onEscape?: () => void;
}): React.ReactElement {
  const ref = useFocusTrap<HTMLDivElement>(active, onEscape ? { onEscape } : {});
  return (
    <div>
      <button type="button">outside-before</button>
      <div ref={ref} data-testid="trap">
        <button type="button">first</button>
        <button type="button">middle</button>
        <button type="button">last</button>
      </div>
      <button type="button">outside-after</button>
    </div>
  );
}

describe('useFocusTrap', () => {
  it('focuses first focusable when active', () => {
    render(<TrapFixture active={true} />);
    expect(screen.getByText('first')).toHaveFocus();
  });

  it('does not steal focus when inactive', () => {
    render(<TrapFixture active={false} />);
    expect(screen.getByText('first')).not.toHaveFocus();
  });

  it('cycles forward Tab from last to first', async () => {
    const user = userEvent.setup();
    render(<TrapFixture active={true} />);
    screen.getByText('last').focus();
    await user.tab();
    expect(screen.getByText('first')).toHaveFocus();
  });

  it('cycles backward Shift+Tab from first to last', async () => {
    const user = userEvent.setup();
    render(<TrapFixture active={true} />);
    screen.getByText('first').focus();
    await user.tab({ shift: true });
    expect(screen.getByText('last')).toHaveFocus();
  });

  it('invokes onEscape when Escape pressed', async () => {
    const user = userEvent.setup();
    const onEscape = vi.fn();
    render(<TrapFixture active={true} onEscape={onEscape} />);
    await user.keyboard('{Escape}');
    expect(onEscape).toHaveBeenCalledTimes(1);
  });
});
