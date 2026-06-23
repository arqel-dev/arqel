import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const seenKeys: string[] = [];
vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations:
    () =>
    (key: string, fallback?: string): string => {
      seenKeys.push(key);
      return `T(${fallback ?? key})`;
    },
}));

import { StateTransition, type StateTransitionFieldProps } from '../src/StateTransition.js';

function baseProps(overrides: Partial<StateTransitionFieldProps> = {}): StateTransitionFieldProps {
  return {
    currentState: null,
    transitions: [],
    history: [],
    showDescription: false,
    showHistory: false,
    transitionsAttribute: 'transitions',
    ...overrides,
  };
}

describe('StateTransition i18n', () => {
  it('localizes the "No state assigned." empty state', () => {
    render(<StateTransition name="status" value={null} props={baseProps()} />);
    expect(seenKeys).toContain('arqel.workflow.no_state_assigned');
    expect(screen.getByTestId('state-transition-empty-state')).toHaveTextContent(
      'T(No state assigned.)',
    );
  });

  it('localizes the "No transitions available." empty state', () => {
    render(
      <StateTransition
        name="status"
        value="draft"
        props={baseProps({
          currentState: { name: 'draft', label: 'Draft' },
          transitions: [],
        })}
      />,
    );
    expect(seenKeys).toContain('arqel.workflow.no_transitions');
    expect(screen.getByTestId('state-transition-empty-transitions')).toHaveTextContent(
      'T(No transitions available.)',
    );
  });
});
