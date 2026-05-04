import type { ActionSchema } from '@arqel-dev/types/actions';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ActionMenu } from '../src/action/ActionMenu.js';

function makeAction(name: string, label: string): ActionSchema {
  return {
    name,
    type: 'row',
    label,
    color: 'primary',
    variant: 'default',
    method: 'POST',
  };
}

describe('ActionMenu', () => {
  it('renders inline buttons when count <= threshold', () => {
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        actions={[makeAction('a', 'Edit'), makeAction('b', 'Restore')]}
        onInvoke={onInvoke}
      />,
    );
    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Restore' })).toBeInTheDocument();
    expect(screen.queryByLabelText('Actions')).toBeNull();
  });

  it('collapses into a dropdown trigger when count > threshold', () => {
    const onInvoke = vi.fn();
    render(
      <ActionMenu
        inlineThreshold={2}
        actions={[makeAction('a', 'Edit'), makeAction('b', 'Restore'), makeAction('c', 'Delete')]}
        onInvoke={onInvoke}
      />,
    );
    expect(screen.getByRole('button', { name: 'Actions' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
  });

  it('returns null when no actions', () => {
    const onInvoke = vi.fn();
    const { container } = render(<ActionMenu actions={[]} onInvoke={onInvoke} />);
    expect(container).toBeEmptyDOMElement();
  });
});
