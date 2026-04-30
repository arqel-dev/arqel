import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  STATE_TRANSITION_EVENT,
  StateTransition,
  type StateTransitionEntry,
  type StateTransitionEventDetail,
  type StateTransitionFieldProps,
  type StateTransitionHistoryEntry,
} from './StateTransition.js';

function makeProps(overrides: Partial<StateTransitionFieldProps> = {}): StateTransitionFieldProps {
  return {
    currentState: { name: 'draft', label: 'Draft', color: null, icon: null },
    transitions: [],
    history: [],
    showDescription: false,
    showHistory: false,
    transitionsAttribute: 'state',
    ...overrides,
  };
}

describe('<StateTransition>', () => {
  it('renders the current state label inside the pill', () => {
    render(
      <StateTransition
        name="state"
        value="draft"
        props={makeProps({
          currentState: { name: 'draft', label: 'Draft', color: '#cccccc', icon: null },
        })}
      />,
    );

    const pill = screen.getByTestId('state-transition-pill');
    expect(pill).toHaveTextContent('Draft');
    expect(pill).toHaveAttribute('data-state', 'draft');
  });

  it('renders only authorized transitions when showDescription is false', () => {
    const transitions: StateTransitionEntry[] = [
      { from: 'draft', to: 'published', label: 'Publish', authorized: true },
      { from: 'draft', to: 'archived', label: 'Archive', authorized: false },
    ];

    render(
      <StateTransition
        name="state"
        value="draft"
        props={makeProps({ transitions, showDescription: false })}
      />,
    );

    const buttons = screen.getAllByTestId('state-transition-button');
    expect(buttons).toHaveLength(1);
    expect(buttons[0]).toHaveTextContent('Publish');
    expect(buttons[0]).not.toBeDisabled();
  });

  it('renders non-authorized transitions disabled when showDescription is true', () => {
    const transitions: StateTransitionEntry[] = [
      { from: 'draft', to: 'published', label: 'Publish', authorized: true },
      { from: 'draft', to: 'archived', label: 'Archive', authorized: false },
    ];

    render(
      <StateTransition
        name="state"
        value="draft"
        props={makeProps({ transitions, showDescription: true })}
      />,
    );

    const buttons = screen.getAllByTestId('state-transition-button');
    expect(buttons).toHaveLength(2);

    const archive = buttons.find((b) => b.textContent === 'Archive');
    expect(archive).toBeDefined();
    expect(archive).toBeDisabled();
    expect(archive).toHaveAttribute('data-authorized', 'false');
  });

  it('dispatches arqel:state-transition CustomEvent on authorized click', () => {
    const transitions: StateTransitionEntry[] = [
      { from: 'draft', to: 'published', label: 'Publish', authorized: true },
    ];
    const listener = vi.fn();
    document.addEventListener(STATE_TRANSITION_EVENT, listener as EventListener);

    render(
      <StateTransition
        name="state"
        value="draft"
        props={makeProps({ transitions })}
        record={{ id: 42 }}
      />,
    );

    fireEvent.click(screen.getByTestId('state-transition-button'));

    expect(listener).toHaveBeenCalledTimes(1);
    const evt = listener.mock.calls[0]?.[0] as CustomEvent<StateTransitionEventDetail>;
    expect(evt.detail).toEqual({
      from: 'draft',
      to: 'published',
      name: 'state',
      recordId: 42,
    });

    document.removeEventListener(STATE_TRANSITION_EVENT, listener as EventListener);
  });

  it('renders the history timeline when showHistory and history.length > 0', () => {
    const history: StateTransitionHistoryEntry[] = [
      { from: 'draft', to: 'published', at: '2026-04-30T10:00:00Z', by: 'alice' },
      { from: 'published', to: 'archived', at: '2026-05-01T11:00:00Z', by: null },
    ];

    render(
      <StateTransition
        name="state"
        value="archived"
        props={makeProps({
          currentState: { name: 'archived', label: 'Archived', color: null, icon: null },
          history,
          showHistory: true,
        })}
      />,
    );

    const timeline = screen.getByTestId('state-transition-history');
    expect(timeline.tagName).toBe('OL');

    const items = screen.getAllByTestId('state-transition-history-item');
    expect(items).toHaveLength(2);
    expect(items[0]).toHaveTextContent('draft');
    expect(items[0]).toHaveTextContent('published');
    expect(items[0]).toHaveTextContent('alice');
    expect(items[1]).toHaveTextContent('archived');
  });

  it('shows "No state assigned." when currentState is null without crashing', () => {
    render(<StateTransition name="state" value={null} props={makeProps({ currentState: null })} />);

    expect(screen.getByTestId('state-transition-empty-state')).toHaveTextContent(
      'No state assigned.',
    );
    expect(screen.queryByTestId('state-transition-pill')).toBeNull();
  });

  it('shows "No transitions available." when transitions list is empty', () => {
    render(<StateTransition name="state" value="draft" props={makeProps({ transitions: [] })} />);

    expect(screen.getByTestId('state-transition-empty-transitions')).toHaveTextContent(
      'No transitions available.',
    );
  });

  it('invokes onTransition callback instead of dispatching event when provided', () => {
    const transitions: StateTransitionEntry[] = [
      { from: 'draft', to: 'published', label: 'Publish', authorized: true },
    ];
    const onTransition = vi.fn();
    const eventListener = vi.fn();
    document.addEventListener(STATE_TRANSITION_EVENT, eventListener as EventListener);

    render(
      <StateTransition
        name="state"
        value="draft"
        props={makeProps({ transitions })}
        onTransition={onTransition}
      />,
    );

    fireEvent.click(screen.getByTestId('state-transition-button'));

    expect(onTransition).toHaveBeenCalledWith('draft', 'published');
    expect(eventListener).not.toHaveBeenCalled();

    document.removeEventListener(STATE_TRANSITION_EVENT, eventListener as EventListener);
  });
});
